<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\InvoiceProcessor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadInvoice')
                ->label('Upload invoice')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('invoice')
                        ->label('PDF invoice')
                        ->required()
                        ->acceptedFileTypes(['application/pdf'])
                        ->rules(['extensions:pdf'])
                        ->maxSize(3072)
                        ->storeFiles(false)
                        ->previewable(false),
                ])
                ->modalHeading('Upload invoice')
                ->modalSubmitActionLabel('Process invoice')
                ->action(function (array $data): void {
                    $file = $data['invoice'] ?? null;

                    if (! $file instanceof TemporaryUploadedFile) {
                        Notification::make()
                            ->title('Invalid upload')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $path = $file->getRealPath();

                        if ($path === false) {
                            throw new RuntimeException(
                                'Temporary PDF file is unavailable.'
                            );
                        }

                        $invoice = app(InvoiceProcessor::class)
                            ->process($path);


                        $needsAttention = $invoice->status === 'NEEDS_ATTENTION';

                        Notification::make()
                            ->title($needsAttention
                                ? "Invoice #{$invoice->id} needs attention"
                                : "Invoice #{$invoice->id} processed")
                            ->body($needsAttention
                                ? implode(' ', $invoice->validation_errors ?? [])
                                : 'Ready for review.')
                            ->status($needsAttention ? 'warning' : 'success')
                            ->send();
                    } catch (ConnectionException | RequestException $e) {
                        report($e);

                        Notification::make()
                            ->title('AI processing unavailable')
                            ->body('Please check your Ollama connection.')
                            ->danger()
                            ->send();
                    } catch (RuntimeException $e) {
                        Notification::make()
                            ->title('Invoice processing failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
