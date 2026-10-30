<?php

namespace App\Http\Controllers;

use App\Services\PdfTextExtractor;
use Illuminate\Http\Request;
use RuntimeException;

class InvoiceController extends Controller
{
    public function upload()
    {
        return view('invoices.upload');
    }

    public function process(Request $request, PdfTextExtractor $extractor)
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

            return view('invoices.upload', [
                'text' => $text
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors([
                'invoice' => $e->getMessage()
            ]);
        }
    }
}
