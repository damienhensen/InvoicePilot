<?php

namespace Tests\Feature;

use App\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\InvoiceTestData;
use Tests\TestCase;

final class InvoiceProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function pdf(): string
    {
        return dirname(__DIR__) . '/Fixtures/valid-invoice.pdf';
    }

    public function test_processes_pdf_and_persists_extracted_invoice(): void
    {
        $data = InvoiceTestData::valid();
        Http::fake(['*' => InvoiceTestData::ollamaResponse($data)]);

        $invoice = app(InvoiceProcessor::class)->process($this->pdf());

        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(InvoiceStatus::ReadyForReview, $invoice->status);
        $this->assertSame($data, $invoice->original_ai_data);
        $this->assertSame($data, $invoice->current_data);
        $this->assertSame([], $invoice->validation_errors);
        $this->assertSame('zaantech diensten b.v.', $invoice->supplier_name_normalized);
        $this->assertGreaterThanOrEqual(0, $invoice->processing_time_ms);
        Http::assertSentCount(1);
    }

    public function test_second_upload_is_flagged_as_duplicate(): void
    {
        Http::fake(['*' => InvoiceTestData::ollamaResponse(InvoiceTestData::valid())]);
        $processor = app(InvoiceProcessor::class);

        $first = $processor->process($this->pdf());
        $second = $processor->process($this->pdf());

        $this->assertSame(InvoiceStatus::ReadyForReview, $first->status);
        $this->assertSame(InvoiceStatus::NeedsAttention, $second->status);
        $this->assertContains('Possible duplicate invoice.', $second->validation_errors);
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_missing_required_data_gets_needs_attention(): void
    {
        Http::fake(['*' => InvoiceTestData::ollamaResponse(
            InvoiceTestData::valid(['invoice_number' => null])
        )]);

        $invoice = app(InvoiceProcessor::class)->process($this->pdf());

        $this->assertSame(InvoiceStatus::NeedsAttention, $invoice->status);
        $this->assertContains('Invoice number is missing.', $invoice->validation_errors);
    }

    public function test_invalid_json_does_not_persist_invoice(): void
    {
        Http::fake(['*' => InvoiceTestData::ollamaResponse('invalid json')]);

        try {
            app(InvoiceProcessor::class)->process($this->pdf());
            $this->fail('Expected invalid JSON to fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('JSON', $e->getMessage());
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_connection_failure_does_not_persist_invoice(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        try {
            app(InvoiceProcessor::class)->process($this->pdf());
            $this->fail('Expected connection error.');
        } catch (ConnectionException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('invoices', 0);
    }
}
