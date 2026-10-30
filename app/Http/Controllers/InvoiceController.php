<?php

namespace App\Http\Controllers;

use App\Services\AiInvoiceExtractor;
use App\Services\InvoiceValidator;
use App\Services\PdfTextExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
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
            $invoice = $aiExtractor->extract($text);
            $warnings = $validator->validate($invoice);

            $status = empty($warnings) ? 'READY_FOR_REVIEW' : 'NEEDS_ATTENTION';

            return view('invoices.upload', compact(
                'invoice',
                'warnings',
                'status'
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
