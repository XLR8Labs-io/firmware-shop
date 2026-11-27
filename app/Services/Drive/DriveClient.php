<?php

namespace App\Services\Drive;

use Closure;
use Generator;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Read-only access to Google Drive (API v3) as the store's service account.
 *
 * Every request, including the OAuth token request, goes through Laravel's HTTP
 * client, so tests can fake all of it.
 */
class DriveClient
{
    private const string API_URL = 'https://www.googleapis.com/drive/v3';

    private const string SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    private const string TOKEN_CACHE_KEY = 'google-drive-access-token';

    private const string FILE_FIELDS = 'id,name,mimeType,size,md5Checksum';

    public function __construct(
        #[Config('services.google_drive.credentials')] private ?string $credentialsPath,
        #[Config('services.google_drive.shared_drive_id')] private ?string $sharedDriveId,
    ) {}

    public function metadata(string $fileId): DriveFile
    {
        $response = $this->request()->get('files/'.rawurlencode($fileId), [
            'fields' => self::FILE_FIELDS,
            'supportsAllDrives' => 'true',
        ]);

        return DriveFile::fromApi($this->json($response));
    }

    /**
     * Starts streaming a file's content. The caller reads the body and checks the
     * status; Range is passed through, so downloads can resume.
     */
    public function openStream(string $fileId, ?string $range = null): Response
    {
        return Http::baseUrl(self::API_URL)
            ->withToken($this->accessToken())
            ->withOptions(['stream' => true])
            ->connectTimeout(15)
            // No overall limit: a multi-gigabyte file can take hours on a slow connection.
            ->timeout(0)
            ->withHeaders(array_filter(['Range' => $range]))
            ->get('files/'.rawurlencode($fileId), [
                'alt' => 'media',
                'supportsAllDrives' => 'true',
            ]);
    }

    /**
     * Everything directly inside a folder, following Drive's pages of up to 1000 items.
     *
     * @return Generator<int, DriveFile>
     */
    public function listChildren(string $folderId): Generator
    {
        $query = [
            'q' => sprintf("'%s' in parents and trashed = false", addcslashes($folderId, "\\'")),
            'fields' => 'nextPageToken,files('.self::FILE_FIELDS.')',
            'pageSize' => 1000,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ];

        // Drive requires driveId whenever corpora=drive is used.
        if (filled($this->sharedDriveId)) {
            $query['corpora'] = 'drive';
            $query['driveId'] = $this->sharedDriveId;
        }

        do {
            $page = $this->json($this->request()->get('files', $query));

            foreach ((array) ($page['files'] ?? []) as $file) {
                yield DriveFile::fromApi((array) $file);
            }

            $query['pageToken'] = $page['nextPageToken'] ?? null;
        } while (is_string($query['pageToken']));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::API_URL)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30)
            ->retry(3, 500, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        if (! $response->successful()) {
            throw DriveException::fromResponse($response);
        }

        return (array) $response->json();
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached)) {
            return $cached;
        }

        $token = (new ServiceAccountCredentials(self::SCOPE, $this->credentials()))
            ->fetchAuthToken($this->sendThroughLaravel());

        if (! isset($token['access_token']) || ! is_string($token['access_token'])) {
            $reason = $token['error_description'] ?? $token['error'] ?? 'no reason given';

            throw new DriveException('Google refused the service account sign-in: '.(is_string($reason) ? $reason : 'no reason given').'.');
        }

        $expiresIn = is_numeric($token['expires_in'] ?? null) ? (int) $token['expires_in'] : 3600;

        // Renew a minute early so a token never runs out in the middle of a request.
        Cache::put(self::TOKEN_CACHE_KEY, $token['access_token'], now()->addSeconds(max(60, $expiresIn - 60)));

        return $token['access_token'];
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        if (blank($this->credentialsPath)) {
            throw new DriveException('Google Drive is not set up: GOOGLE_SA_JSON is empty.');
        }

        $path = str_starts_with($this->credentialsPath, '/') ? $this->credentialsPath : base_path($this->credentialsPath);

        if (! is_file($path)) {
            throw new DriveException('Google Drive is not set up: the service account key file named in GOOGLE_SA_JSON was not found.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials)) {
            throw new DriveException('Google Drive is not set up: the service account key file is not valid JSON.');
        }

        return $credentials;
    }

    /**
     * google/auth builds and signs the token request; this sends it with Laravel's HTTP client.
     */
    private function sendThroughLaravel(): Closure
    {
        return fn (RequestInterface $request): ResponseInterface => Http::withHeaders(
            array_map(fn (array $values): string => implode(', ', $values), $request->getHeaders()),
        )
            ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type'))
            ->timeout(30)
            ->send($request->getMethod(), (string) $request->getUri())
            ->toPsrResponse();
    }
}
