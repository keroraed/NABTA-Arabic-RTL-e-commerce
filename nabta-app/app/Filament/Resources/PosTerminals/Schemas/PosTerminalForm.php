<?php

namespace App\Filament\Resources\PosTerminals\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PosTerminalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required(),
                Select::make('cash_account_id')
                    ->relationship('cashAccount', 'name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('last_synced_at'),
            ]);
    }
}
