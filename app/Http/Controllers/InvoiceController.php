<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\AiInvoiceExtractor;
use App\Services\InvoiceValidator;
use App\Services\PdfTextExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceController extends Controller
{
    public function upload()
    {
        return view('invoices.upload');
    }

    public function process(Request $request, PdfTextExtractor $extractor, AiInvoiceExtractor $aiExtractor, InvoiceValidator $validator)
    {
        $request->validate([
            'invoice' => [
                'required',
                'file',
                'mimes:pdf',
                'extensions:pdf',
                'max:3072'
            ]
        ]);

        try {
            $text = $extractor->extract($request->file('invoice')->getPathname());

            $startedAt = hrtime(true);

            // Extract invoice data
            $invoice = $aiExtractor->extract($text);

            $processingTimeMs = (int) round(
                (hrtime(true) - $startedAt) / 1_000_000
            );

            // Validate indepentenly
            $warnings = $validator->validate($invoice);

            $status = empty($warnings) ? 'READY_FOR_REVIEW' : 'NEEDS_ATTENTION';

            // Normalize supplier name
            $supplierName = $invoice['supplier_name'];

            $normalizedSupplier = $supplierName !== null
                && trim($supplierName) !== ''
                ? Str::lower(Str::squish($supplierName))
                : null;

            // Persist extraction and validation results.
            $savedInvoice = Invoice::create([
                'supplier_name_normalized' => $normalizedSupplier,
                'invoice_number' => $invoice['invoice_number'],
                'original_ai_data' => $invoice,
                'current_data' => $invoice,
                'validation_errors' => $warnings,
                'status' => $status,
                'model_name' => 'qwen3:4b-instruct',
                'processing_time_ms' => $processingTimeMs,
            ]);

            return view('invoices.upload', compact(
                'invoice',
                'warnings',
                'status',
                'savedInvoice'
            ));
        } catch (ConnectionException | RequestException $e) {
            report($e);

            return back()->withErrors([
                'invoice' => 'AI processing is currently unavailable. Please try again.',
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors([
                'invoice' => $e->getMessage()
            ]);
        }
    }
}
