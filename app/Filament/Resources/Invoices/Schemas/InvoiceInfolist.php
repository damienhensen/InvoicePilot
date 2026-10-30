<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('supplier_name_normalized')
                    ->placeholder('-'),
                TextEntry::make('invoice_number')
                    ->placeholder('-'),
                TextEntry::make('original_ai_data')
                    ->columnSpanFull(),
                TextEntry::make('current_data')
                    ->columnSpanFull(),
                TextEntry::make('validation_errors')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('model_name'),
                TextEntry::make('processing_time_ms')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
