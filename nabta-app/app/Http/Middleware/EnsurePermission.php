<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission check, for the Blade screens that live outside the
 * Filament panel (the till and the printable reports).
 *
 * Passing several permissions means "any of these", which is what the reports
 * want: an accountant reaches them through accounting.view, an owner through
 * being an admin.
 *
 * Usage: ->middleware('permission:pos.use')
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($user && $user->can($permission)) {
                return $next($request);
            }
        }

        abort(403, __('errors.forbidden'));
    }
}
