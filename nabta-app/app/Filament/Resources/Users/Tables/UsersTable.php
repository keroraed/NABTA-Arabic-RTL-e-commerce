<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('users.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record) => $record->email),

                TextColumn::make('role')
                    ->label(__('users.role'))
                    ->badge()
                    ->formatStateUsing(fn (Role $state) => $state->label())
                    ->color(fn (Role $state) => match ($state) {
                        Role::Admin => 'danger',
                        Role::Accountant => 'warning',
                        Role::Cashier => 'info',
                    }),

                TextColumn::make('permissions')
                    ->label(__('users.permissions'))
                    ->state(fn (User $record) => $record->permissions === null
                        ? __('users.role_defaults')
                        : __('users.custom_count', ['count' => count($record->effectivePermissions())]))
                    ->color(fn (User $record) => $record->permissions === null ? 'gray' : 'warning')
                    ->badge(),

                ToggleColumn::make('is_active')
                    ->label(__('users.is_active'))
                    // Locking yourself out of your own admin panel mid-session
                    // is not a mistake worth allowing.
                    ->disabled(fn (User $record) => $record->id === auth()->id()),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('users.role'))->options(Role::options()),
                TernaryFilter::make('is_active')->label(__('users.is_active')),
            ])
            ->recordActions([
                EditAction::make()->label(__('users.edit')),
                DeleteAction::make()
                    ->label(__('users.delete'))
                    ->visible(fn (User $record) => $record->id !== auth()->id()),
            ])
            ->emptyStateHeading(__('users.empty'));
    }
}
