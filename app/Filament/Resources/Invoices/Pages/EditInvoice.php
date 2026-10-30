<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceValidator;
use Filament\Actions\Action;
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
            Action::make('approve')
                ->label('Approve invoice')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve invoice?')
                ->modalDescription(
                    'Save your corrections before approving. ' .
                        'The saved invoice will be validated again.'
                )
                ->visible(
                    fn(): bool =>
                    $this->getRecord()->status !== InvoiceStatus::Approved
                        && $this->getRecord()->status === InvoiceStatus::ReadyForReview
                )
                ->action(function (): void {
                    $record = $this->getRecord()->fresh();

                    // Never approve the same invoice twice.
                    if ($record->status === InvoiceStatus::Approved) {
                        return;
                    }

                    // Reuse our existing validation and duplicate checks.
                    $record = $this->handleRecordUpdate($record, [
                        'current_data' => $record->current_data,
                    ]);

                    if ($record->status !== InvoiceStatus::ReadyForReview) {
                        Notification::make()
                            ->title('Invoice cannot be approved')
                            ->body(implode(' ', $record->validation_errors ?? []))
                            ->warning()
                            ->send();

                        return;
                    }

                    // Explicit manual approval.
                    $record->update([
                        'status' => InvoiceStatus::Approved,
                    ]);

                    Notification::make()
                        ->title('Invoice approved')
                        ->success()
                        ->send();

                    $this->redirect(
                        InvoiceResource::getUrl('index')
                    );
                }),
        ];
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        abort_if(
            $record->fresh()->status === InvoiceStatus::Approved,
            403,
            'Approved invoices cannot be modified.'
        );

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
