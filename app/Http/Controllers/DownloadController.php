<?php

namespace App\Http\Controllers;

use App\Enums\DownloadStatus;
use App\Jobs\SendAdminAlert;
use App\Models\DownloadToken;
use App\Models\Product;
use App\Models\User;
use App\Services\Downloads\DownloadNotAllowed;
use App\Services\Downloads\DownloadService;
use App\Services\Drive\DriveClient;
use App\Services\Drive\DriveException;
use App\Services\SiteSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Client\Response as DriveResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    /**
     * The Download button: checks the customer may have the file, then sends them
     * to a short-lived link that works without a session (download managers, phones).
     */
    public function store(Request $request, #[CurrentUser] User $user, Product $product, DownloadService $downloads): RedirectResponse
    {
        try {
            $link = $downloads->issueLink($user, $product, (string) $request->ip(), $request->userAgent());
        } catch (DownloadNotAllowed $exception) {
            return back()->with('download_error', $exception->getMessage());
        }

        return redirect()->route('downloads.stream', ['token' => $link->token, 'fileName' => $product->file_name]);
    }

    /**
     * Streams the file from Google Drive through this server, passing Range through
     * so downloads can resume and download managers can fetch parts in parallel.
     */
    public function stream(Request $request, string $token, DriveClient $drive, SiteSettings $settings): Response
    {
        $link = DownloadToken::query()->with(['product', 'log'])->where('token', $token)->first();

        abort_if($link === null || $link->expires_at->isPast(), 404, __('This download link has expired. Open the file page and click Download again.'));

        $ip = (string) $request->ip();

        if (! $link->admitIp($ip, $settings->maxIpsPerDownloadLink())) {
            SendAdminAlert::dispatch("Download link for \"{$link->product->title}\" (user #{$link->user_id}) was refused for a new IP {$ip}: too many IPs used it.");

            abort(403, __('This download link has been used from too many places. Click Download on the file page to get a new one.'));
        }

        $range = $request->header('Range');
        $range = is_string($range) && preg_match('/^bytes=\d*-\d*(,\s*\d*-\d*)*$/', $range) === 1 ? $range : null;

        [$fromDrive, $usedFile] = $this->openFromDrive($drive, $link, $range);

        if ($fromDrive === null) {
            $link->log?->update(['status' => DownloadStatus::QuotaExceeded]);
            SendAdminAlert::dispatch("Google Drive's download quota was reached for \"{$link->product->title}\" (product #{$link->product_id})".($link->product->mirror_drive_file_id ? ' and its mirror.' : '. It has no mirror file.'));

            return response()->view('downloads.busy', ['supportContact' => $settings->get('support_contact_text')], 503);
        }

        if ($fromDrive->status() === 416) {
            return response('', 416, array_filter(['Content-Range' => $fromDrive->header('Content-Range')]));
        }

        if (! $fromDrive->successful()) {
            $link->log?->update(['status' => DownloadStatus::Error]);
            report(DriveException::fromResponse($fromDrive));

            abort(502, __('The file could not be fetched right now. Please try again in a few minutes.'));
        }

        $link->log?->update(['drive_file_used' => $usedFile]);
        $link->recordUse();

        $headers = array_filter([
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $link->product->file_name, $this->asciiFileName($link->product->file_name)),
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $fromDrive->header('Content-Length'),
            'Content-Range' => $fromDrive->header('Content-Range'),
            'Cache-Control' => 'private, no-store',
        ]);

        return new StreamedResponse(function () use ($fromDrive, $link): void {
            set_time_limit(0);
            $body = $fromDrive->toPsrResponse()->getBody();
            $sent = 0;

            try {
                while (! $body->eof() && ! connection_aborted()) {
                    $chunk = $body->read(1024 * 1024);
                    $sent += strlen($chunk);
                    echo $chunk;
                    flush();
                }
            } finally {
                DownloadToken::query()->whereKey($link->id)->increment('bytes_served', $sent);
            }
        }, $fromDrive->status(), $headers);
    }

    /**
     * Opens the main file, falling back to the mirror when Google's per-file download
     * quota is reached. Returns null for the response when neither can be downloaded now.
     *
     * @return array{0: DriveResponse|null, 1: string}
     */
    private function openFromDrive(DriveClient $drive, DownloadToken $link, ?string $range): array
    {
        $response = $drive->openStream($link->product->drive_file_id, $range);

        if (! DriveException::isQuotaLimit($response)) {
            return [$response, 'primary'];
        }

        $mirror = $link->product->mirror_drive_file_id;

        if (blank($mirror)) {
            return [null, 'primary'];
        }

        $response = $drive->openStream($mirror, $range);

        return DriveException::isQuotaLimit($response) ? [null, 'mirror'] : [$response, 'mirror'];
    }

    private function asciiFileName(string $fileName): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]|[%\/\\\\"]/', '_', $fileName);

        return $ascii === null || $ascii === '' ? 'download' : $ascii;
    }
}
