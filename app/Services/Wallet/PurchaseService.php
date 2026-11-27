<?php

namespace App\Services\Wallet;

use App\Enums\LedgerEntryType;
use App\Enums\ProductStatus;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Services\FolderTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseService
{
    public function __construct(private WalletService $wallet, private FolderTree $folderTree) {}

    /**
     * Buy a paid file from the wallet. Buying a file already owned returns the
     * existing purchase without charging again.
     *
     * @param  string  $confirmedPrice  The price the customer saw and agreed to; if it has
     *                                  changed since, nothing is charged.
     *
     * @throws InsufficientFunds
     * @throws PurchaseNotAllowed
     */
    public function buy(User $user, Product $product, string $confirmedPrice): Purchase
    {
        return DB::transaction(function () use ($user, $product, $confirmedPrice): Purchase {
            // The same lock WalletService::debit takes, held first so the "already bought?" check is serialized too.
            $current = User::query()->whereKey($user->id)->lockForUpdate()->sole();

            if ($current->is_banned) {
                throw PurchaseNotAllowed::banned($current->ban_reason);
            }

            $product = Product::query()->findOrFail($product->id);
            $purchase = Purchase::query()->where('user_id', $user->id)->where('product_id', $product->id)->first();

            if ($purchase?->status === PurchaseStatus::Active) {
                return $purchase;
            }

            if ($product->status !== ProductStatus::Published || $product->isFree() || ! $this->folderTree->isVisible($product->folder_id)) {
                throw PurchaseNotAllowed::notForSale();
            }

            if (! is_numeric($confirmedPrice) || bccomp($product->price, $confirmedPrice, 2) !== 0) {
                throw PurchaseNotAllowed::priceChanged($product->price);
            }

            $payment = $this->wallet->debit(
                $user,
                $product->price,
                LedgerEntryType::Purchase,
                "purchase:{$user->id}:{$product->id}:".Str::uuid(),
                $product->title,
            );

            $attributes = [
                'price_paid' => $product->price,
                'ledger_entry_id' => $payment->id,
                'status' => PurchaseStatus::Active,
                'refunded_at' => null,
            ];

            // A refunded purchase is reactivated rather than duplicated: one row per user and file.
            if ($purchase !== null) {
                $purchase->update($attributes);

                return $purchase;
            }

            return Purchase::query()->create([...$attributes, 'user_id' => $user->id, 'product_id' => $product->id]);
        });
    }

    /**
     * Return what was paid to the wallet and remove access to the file.
     *
     * @throws PurchaseNotAllowed
     */
    public function refund(Purchase $purchase, User $admin, ?string $note = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $admin, $note): Purchase {
            $purchase = Purchase::query()->with('user')->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== PurchaseStatus::Active) {
                throw PurchaseNotAllowed::notRefundable();
            }

            // Tied to the payment being refunded, so a re-bought file can be refunded again but no payment twice.
            $this->wallet->credit(
                $purchase->user,
                $purchase->price_paid,
                LedgerEntryType::Refund,
                "refund:{$purchase->id}:{$purchase->ledger_entry_id}",
                $note ?: "Refund of purchase #{$purchase->id}",
                $admin,
            );

            $purchase->update(['status' => PurchaseStatus::Refunded, 'refunded_at' => now()]);

            return $purchase;
        });
    }
}
