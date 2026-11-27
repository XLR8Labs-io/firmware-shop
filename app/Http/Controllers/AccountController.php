<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(#[CurrentUser] User $user, WalletService $wallet): View
    {
        return view('dashboard', [
            'balance' => $wallet->balance($user),
            'recentPurchases' => $user->purchases()->with('product')->latest('updated_at')->limit(5)->get(),
            'recentEntries' => $user->ledgerEntries()->latest('id')->limit(5)->get(),
        ]);
    }

    public function purchases(#[CurrentUser] User $user): View
    {
        return view('account.purchases', [
            'purchases' => $user->purchases()->with('product')->latest('updated_at')->paginate(20),
        ]);
    }

    public function wallet(#[CurrentUser] User $user, WalletService $wallet): View
    {
        return view('account.wallet', [
            'balance' => $wallet->balance($user),
            'entries' => $user->ledgerEntries()->latest('id')->paginate(30),
        ]);
    }
}
