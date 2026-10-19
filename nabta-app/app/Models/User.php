<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'permissions', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------------
     | Access
     --------------------------------------------------------------------- */

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * The permissions this user actually has.
     *
     * A null `permissions` column means "whatever the role says", which is the
     * normal case. A non-null array is an explicit per-user override, so an
     * admin can widen or narrow one person without inventing a new role.
     *
     * @return array<int, string>
     */
    public function effectivePermissions(): array
    {
        if ($this->permissions !== null) {
            return array_values(array_intersect(
                $this->permissions,
                array_column(Permission::cases(), 'value'),
            ));
        }

        return array_map(
            fn (Permission $p) => $p->value,
            ($this->role ?? Role::Cashier)->defaultPermissions(),
        );
    }

    public function hasPermission(Permission|string $permission): bool
    {
        // An inactive account keeps its row but loses every door.
        if (! $this->is_active) {
            return false;
        }

        // The owner is never locked out of their own shop by a permission list.
        if ($this->isAdmin()) {
            return true;
        }

        $value = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($value, $this->effectivePermissions(), true);
    }

    /** Whether this user has any reason to see the admin panel at all. */
    public function canAccessAdminPanel(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        // A cashier has the till and nothing else, so the panel would be an
        // empty shell — send them straight to the POS instead.
        return collect($this->effectivePermissions())
            ->contains(fn (string $p) => ! in_array($p, [
                Permission::PosUse->value,
                Permission::PosRefund->value,
            ], true));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessAdminPanel();
    }

    /** Where this user should land after signing in. */
    public function homeRoute(): string
    {
        return $this->canAccessAdminPanel()
            ? route('filament.admin.pages.dashboard')
            : route('pos.index');
    }
}
