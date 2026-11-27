<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Models\MfsDeposit;
use App\Services\Deposits\PipraPayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * PipraPay tells us about payments in two ways: a webhook, and the customer coming back
 * from its checkout page. Neither is trusted; both only trigger a fresh check with PipraPay.
 */
class PipraPayController extends Controller
{
    public function webhook(Request $request, PipraPayGateway $gateway): JsonResponse
    {
        $paymentId = $request->input('pp_id');

        $deposit = is_string($paymentId) && $paymentId !== ''
            ? MfsDeposit::query()->where('source', 'piprapay')->where('gateway_payment_id', $paymentId)->first()
            : null;

        if ($deposit !== null) {
            try {
                $gateway->confirm($deposit);
            } catch (RuntimeException $exception) {
                report($exception);

                // PipraPay sends the webhook again later; the scheduled check also covers it.
                return response()->json(['received' => false], 503);
            }
        }

        return response()->json(['received' => true]);
    }

    public function returned(Request $request, MfsDeposit $deposit, PipraPayGateway $gateway): RedirectResponse
    {
        abort_unless($deposit->user_id === $request->user()?->id, 404);

        try {
            $deposit = $gateway->confirm($deposit);

            $message = match ($deposit->status) {
                DepositStatus::Approved => __('Payment received. $:amount was added to your wallet.', ['amount' => $deposit->amount_usd]),
                DepositStatus::Rejected => __('This payment was not added: :reason', ['reason' => (string) $deposit->admin_note]),
                DepositStatus::Pending => __('Your payment is being confirmed. Your wallet updates on its own once it is, usually within a few minutes.'),
            };
        } catch (RuntimeException $exception) {
            report($exception);
            $message = __('We could not check your payment just now. It will be checked again automatically.');
        }

        return to_route('account.add-funds', ['method' => $deposit->method->value])->with('deposit_notice', $message);
    }
}
