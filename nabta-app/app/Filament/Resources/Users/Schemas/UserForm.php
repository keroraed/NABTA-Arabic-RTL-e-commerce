<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Permission;
use App\Enums\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('users.account'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('users.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('users.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label(__('users.password'))
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            // Required when creating; left blank on edit means
                            // "leave the existing password alone".
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? __('users.password_edit_hint')
                                : null),

                        Toggle::make('is_active')
                            ->label(__('users.is_active'))
                            ->default(true)
                            ->helperText(__('users.is_active_hint')),
                    ]),

                Section::make(__('users.role_and_permissions'))
                    ->description(__('users.permissions_hint'))
                    ->schema([
                        Select::make('role')
                            ->label(__('users.role'))
                            ->options(Role::options())
                            ->default(Role::Cashier->value)
                            ->required()
                            ->live()
                            // Changing the role resets the checkboxes back to
                            // that role's defaults, so the form never shows a
                            // stale set from the previous choice.
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('use_role_defaults', true);
                                $set('permissions', self::defaultsFor($state));
                            }),

                        Toggle::make('use_role_defaults')
                            ->label(__('users.use_role_defaults'))
                            ->default(true)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                if ($state) {
                                    $set('permissions', self::defaultsFor($get('role')));
                                }
                            })
                            ->helperText(__('users.use_role_defaults_hint')),

                        CheckboxList::make('permissions')
                            ->label(__('users.permissions'))
                            ->options(Permission::options())
                            ->descriptions(self::groupDescriptions())
                            ->columns(2)
                            ->bulkToggleable()
                            ->disabled(fn (callable $get) => (bool) $get('use_role_defaults'))
                            // An admin is granted everything by Gate::before, so
                            // showing an editable list here would be a lie.
                            ->visible(fn (callable $get) => $get('role') !== Role::Admin->value)
                            // Null in the column means "follow the role"; only
                            // an explicit override is stored.
                            ->dehydrateStateUsing(fn ($state, callable $get) => $get('use_role_defaults')
                                ? null
                                : array_values($state ?? [])),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    private static function defaultsFor(?string $role): array
    {
        $role = Role::tryFrom($role ?? '') ?? Role::Cashier;

        return array_map(fn (Permission $p) => $p->value, $role->defaultPermissions());
    }

    /** Shows which area each permission belongs to, next to the checkbox. */
    private static function groupDescriptions(): array
    {
        return collect(Permission::cases())
            ->mapWithKeys(fn (Permission $p) => [
                $p->value => __('permissions.groups.'.$p->group()),
            ])
            ->all();
    }
}
