<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice details')
                    ->description('Review and correct the AI-extracted values.')
                    ->schema([
                        TextInput::make('current_data.supplier_name')
                            ->label('Supplier name'),

                        TextInput::make('current_data.invoice_number')
                            ->label('Invoice number'),

                        TextInput::make('current_data.invoice_date')
                            ->label('Invoice date')
                            ->helperText('Format: YYYY-MM-DD'),

                        TextInput::make('current_data.currency')
                            ->label('Currency'),

                        TextInput::make('current_data.subtotal_ex_vat')
                            ->label('Subtotal excl. VAT'),

                        TextInput::make('current_data.vat_amount')
                            ->label('VAT amount'),

                        TextInput::make('current_data.total_in_vat')
                            ->label('Total incl. VAT'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
