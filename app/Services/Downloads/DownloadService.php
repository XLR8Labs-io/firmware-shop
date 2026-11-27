<?php

namespace App\Services\Downloads;

use App\Enums\DownloadStatus;
use App\Enums\ProductStatus;
use App\Models\DownloadLog;
use App\Models\DownloadToken;
use App\Models\Product;
use App\Models\User;
use App\Services\FolderTree;
use App\Services\SiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DownloadService
{
    public function __construct(private SiteSettings $settings, private FolderTree $folderTree) {}

    /**
     * Create a download link for a file the user may download: a free file, or one they own.
     *
     * @throws DownloadNotAllowed
     */
    public function issueLink(User $user, Product $product, string $ip, ?string $userAgent): DownloadToken
    {
        if ($product->status !== ProductStatus::Published || ! $this->folderTree->isVisible($product->folder_id)) {
            throw DownloadNotAllowed::notAvailable();
        }

        if (! $product->isFree() && ! $user->ownsProduct($product)) {
            throw DownloadNotAllowed::notOwned();
        }

        return DB::transaction(function () use ($user, $product, $ip, $userAgent): DownloadToken {
            // Serializes this user's clicks so the daily limit can't be passed by clicking many times at once.
            $current = User::query()->whereKey($user->id)->lockForUpdate()->sole();

            if ($current->is_banned) {
                throw DownloadNotAllowed::banned($current->ban_reason);
            }

            $limit = $this->settings->dailyDownloadLimit();
            $linksToday = DownloadToken::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();

            if ($linksToday >= $limit) {
                throw DownloadNotAllowed::dailyLimitReached($limit);
            }

            $token = DownloadToken::query()->create([
                'token' => Str::random(40),
                'user_id' => $user->id,
                'product_id' => $product->id,
                'expires_at' => now()->addHours($this->settings->downloadLinkHours()),
                'ip_addresses' => [],
            ]);

            DownloadLog::query()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'download_token_id' => $token->id,
                'ip' => $ip,
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 500, ''),
                'status' => DownloadStatus::Started,
            ]);

            return $token;
        });
    }
}
