<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('expense_category_id')
                    ->required()
                    ->numeric(),
                Select::make('cash_account_id')
                    ->relationship('cashAccount', 'name')
                    ->required(),
                Select::make('warehouse_id')
                    ->relationship('warehouse', 'name'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                DatePicker::make('expense_date')
                    ->required(),
                TextInput::make('description'),
                TextInput::make('attachment_path'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
