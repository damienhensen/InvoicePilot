<?php

namespace App\Services;

use App\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Str;

final class InvoiceProcessor
{
    public function __construct(
        private PdfTextExtractor $pdfExtractor,
        private AiInvoiceExtractor $aiExtractor,
        private InvoiceValidator $validator
    ) {}

    public function process(string $path): Invoice
    {
        $text = $this->pdfExtractor->extract($path);

        $startedAt = hrtime(true);

        // Extract invoice data
        $invoice = $this->aiExtractor->extract($text);

        $processingTimeMs = (int) round(
            (hrtime(true) - $startedAt) / 1_000_000
        );

        // Validate indepentenly
        $warnings = $this->validator->validate($invoice);

        // Normalize supplier name
        $supplierName = $invoice['supplier_name'] ?? null;
        $invoiceNumber = $invoice['invoice_number'] ?? null;

        $normalizedSupplier = $supplierName !== null
            && trim($supplierName) !== ''
            ? Str::lower(Str::squish($supplierName))
            : null;

        // Check if this invoice already exists.
        if (
            $normalizedSupplier !== null &&
            is_string($invoiceNumber) &&
            trim($invoiceNumber) !== ''
        ) {
            $duplicateExists = Invoice::query()
                ->where('supplier_name_normalized', $normalizedSupplier)
                ->where('invoice_number', $invoiceNumber)
                ->exists();

            if ($duplicateExists) {
                $warnings[] = 'Possible duplicate invoice.';
            }
        }

        $status = empty($warnings)
            ? InvoiceStatus::ReadyForReview
            : InvoiceStatus::NeedsAttention;

        // Persist extraction and validation results.
        return Invoice::create([
            'supplier_name_normalized' => $normalizedSupplier,
            'invoice_number' => $invoice['invoice_number'],
            'original_ai_data' => $invoice,
            'current_data' => $invoice,
            'validation_errors' => $warnings,
            'status' => $status,
            'model_name' => 'qwen3:4b-instruct',
            'processing_time_ms' => $processingTimeMs,
        ]);
    }
}
