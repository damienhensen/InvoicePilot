<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('current_data.supplier_name')
                    ->label('Supplier'),

                TextColumn::make('current_data.invoice_number')
                    ->label('Invoice number'),

                TextColumn::make('current_data.total_in_vat')
                    ->label('Total')
                    ->prefix('€ '),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('processing_time_ms')
                    ->label('AI time')
                    ->suffix(' ms'),

                TextColumn::make('created_at')
                    ->label('Processed')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->visible(
                    fn(Invoice $record): bool =>
                    $record->status !== InvoiceStatus::Approved
                ),
            ])
            ->toolbarActions([]);
    }
}
