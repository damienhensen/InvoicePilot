<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceValidator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ViewAction::make(),
            // DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        $invoiceData = $data['current_data'];

        $warnings = app(InvoiceValidator::class)
            ->validate($invoiceData);

        $supplier = $invoiceData['supplier_name'] ?? null;
        $number = $invoiceData['invoice_number'] ?? null;

        $normalizedSupplier = is_string($supplier) && trim($supplier) !== ''
            ? Str::lower(Str::squish($supplier))
            : null;

        $number = is_string($number) && trim($number) !== ''
            ? trim($number)
            : null;

        if ($normalizedSupplier !== null && $number !== null) {
            $duplicateExists = Invoice::query()
                ->whereKeyNot($record->getKey())
                ->where('supplier_name_normalized', $normalizedSupplier)
                ->where('invoice_number', $number)
                ->exists();

            if ($duplicateExists) {
                $warnings[] = 'Possible duplicate invoice.';
            }
        }

        $status = empty($warnings)
            ? InvoiceStatus::ReadyForReview
            : InvoiceStatus::NeedsAttention;

        $record->update([
            'current_data' => $invoiceData,
            'supplier_name_normalized' => $normalizedSupplier,
            'invoice_number' => $number,
            'validation_errors' => $warnings,
            'status' => $status,
        ]);

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        $record = $this->getRecord();

        $needsAttention = $record->status === InvoiceStatus::NeedsAttention;

        return Notification::make()
            ->title($needsAttention
                ? 'Invoice saved with warnings'
                : 'Invoice validated successfully')
            ->body($needsAttention
                ? implode(' ', $record->validation_errors ?? [])
                : 'Ready for review.')
            ->status($needsAttention ? 'warning' : 'success');
    }
}
