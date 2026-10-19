<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Turns every Permission case into a Gate ability, so the whole app can use the
 * ordinary can() / @can() vocabulary rather than a bespoke check.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user) => $user->hasPermission($permission),
            );
        }

        // The shop owner is never locked out by a permission list — including
        // one added by a future migration they have not seen yet.
        Gate::before(fn (User $user) => $user->isAdmin() && $user->is_active ? true : null);
    }
}
