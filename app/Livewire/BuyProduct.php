<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\User;
use App\Services\SiteSettings;
use App\Services\Wallet\InsufficientFunds;
use App\Services\Wallet\PurchaseNotAllowed;
use App\Services\Wallet\PurchaseService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The Buy button and its confirmation on a paid file's page.
 */
class BuyProduct extends Component
{
    #[Locked]
    public Product $product;

    /**
     * The price the customer was shown. If the file's price changes before they
     * confirm, nothing is charged and the new price is shown for them to confirm.
     */
    #[Locked]
    public string $shownPrice = '';

    public ?string $problem = null;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->shownPrice = $product->price;
    }

    /**
     * @return numeric-string
     */
    #[Computed]
    public function balance(): string
    {
        return app(WalletService::class)->balance($this->customer());
    }

    /**
     * How much more the customer needs, or 0.00 when they can pay.
     *
     * @return numeric-string
     */
    #[Computed]
    public function shortfall(): string
    {
        $price = is_numeric($this->shownPrice) ? $this->shownPrice : $this->product->price;
        $shortfall = bcsub($price, $this->balance(), 2);

        return bccomp($shortfall, '0', 2) > 0 ? $shortfall : '0.00';
    }

    public function buy(PurchaseService $purchases): void
    {
        $customer = $this->customer();
        abort_unless($customer->hasVerifiedEmail(), 403);

        try {
            $purchases->buy($customer, $this->product, $this->shownPrice);
        } catch (InsufficientFunds $exception) {
            $this->problem = __('Your balance is too low: you need $:amount more.', ['amount' => $exception->shortfall()]);
            unset($this->balance, $this->shortfall);

            return;
        } catch (PurchaseNotAllowed $exception) {
            $this->problem = $exception->getMessage();
            $this->shownPrice = $this->product->refresh()->price;
            unset($this->shortfall);

            return;
        }

        $this->redirectRoute('products.show', $this->product);
    }

    public function render(): View
    {
        return view('livewire.buy-product', [
            'supportContact' => app(SiteSettings::class)->get('support_contact_text'),
        ]);
    }

    private function customer(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
