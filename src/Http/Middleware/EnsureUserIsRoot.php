<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsRoot
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(
            $user === null || ! $user->hasRole((string) config('arkhe.roles.root')),
            403,
        );

        return $next($request);
    }
}
