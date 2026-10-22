<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (! $user->active) {
            auth()->logout();
            abort(403, 'Akun Anda sudah dinonaktifkan.');
        }

        $userRole = $user->role;

        if (! in_array($userRole, $roles, true)) {
            abort(
                403,
                'Anda tidak memiliki izin untuk melakukan tindakan ini.'
            );
        }

        return $next($request);
    }
}
