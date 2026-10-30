<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('supplier_name_normalized'),
                TextInput::make('invoice_number'),
                Textarea::make('original_ai_data')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('current_data')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('validation_errors')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required(),
                TextInput::make('model_name')
                    ->required(),
                TextInput::make('processing_time_ms')
                    ->numeric(),
            ]);
    }
}
