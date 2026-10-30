<?php

namespace Tests\Support;

use App\InvoiceStatus;
use App\Models\Invoice;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class InvoiceTestData
{
    public static function valid(array $changes = []): array
    {
        return array_replace([
            'supplier_name' => 'ZaanTech Diensten B.V.',
            'invoice_number' => 'ZTD-2026-041',
            'invoice_date' => '2026-10-23',
            'subtotal_ex_vat' => '200.00',
            'vat_amount' => '42.00',
            'total_in_vat' => '242.00',
            'currency' => 'EUR',
        ], $changes);
    }

    public static function ollamaResponse(array|string $content): PromiseInterface
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_THROW_ON_ERROR);
        }

        return Http::response(['message' => ['content' => $content]], 200);
    }

    public static function create(array $changes = [], array $attributes = []): Invoice
    {
        $data = self::valid($changes);
        $supplier = $data['supplier_name'];

        return Invoice::create(array_replace([
            'supplier_name_normalized' => is_string($supplier)
                ? Str::lower(Str::squish($supplier))
                : null,
            'invoice_number' => $data['invoice_number'],
            'original_ai_data' => $data,
            'current_data' => $data,
            'validation_errors' => [],
            'status' => InvoiceStatus::ReadyForReview,
            'model_name' => 'test-model',
            'processing_time_ms' => 100,
        ], $attributes));
    }
}
