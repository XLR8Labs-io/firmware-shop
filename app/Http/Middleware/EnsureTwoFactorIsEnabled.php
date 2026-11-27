<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users without confirmed two-factor authentication to the security settings
 * page, where they can turn it on. Used to guard the admin panel.
 */
class EnsureTwoFactorIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
