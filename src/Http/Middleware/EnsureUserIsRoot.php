<?php

declare(strict_types=1);

namespace Arkhe\Main\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the sensitive "root" area (roles & permissions management).
 * Despite the class/middleware name, the actual check is a permission
 * (`manage-roles` by default, overridable via `arkhe.root_permission`).
 * The naming stays for backwards compatibility — drop-in callers do not
 * need to change.
 */
class EnsureUserIsRoot
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $permission = (string) config('arkhe.root_permission', 'manage-roles');

        abort_if(
            $user === null || ! method_exists($user, 'can') || ! $user->can($permission),
            403,
        );

        return $next($request);
    }
}
