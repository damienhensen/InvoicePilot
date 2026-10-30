<?php

namespace Tests\Feature;

use App\Services\AiInvoiceExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\InvoiceTestData;
use Tests\TestCase;

final class AiInvoiceExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_extracts_valid_structured_data_from_mocked_ollama(): void
    {
        // Use a different model to verify the configuration is respected.
        config()->set('services.ollama.model', 'test-model:latest');

        $expected = InvoiceTestData::valid();

        Http::fake([
            '*' => InvoiceTestData::ollamaResponse($expected),
        ]);

        $actual = app(AiInvoiceExtractor::class)
            ->extract('Fictieve factuurtekst');

        $this->assertSame($expected, $actual);

        Http::assertSentCount(1);

        Http::assertSent(
            fn($request) =>
            $request->url() === 'http://127.0.0.1:11434/api/chat'
                && $request['model'] === 'test-model:latest'
                && $request['stream'] === false
                && ($request['format']['additionalProperties'] ?? null) === false
        );
    }

    public function test_rejects_invalid_json(): void
    {
        Http::fake(['*' => InvoiceTestData::ollamaResponse('{broken-json')]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error parsing JSON');

        app(AiInvoiceExtractor::class)->extract('Invoice text');
    }

    public function test_rejects_missing_required_key(): void
    {
        $data = InvoiceTestData::valid();
        unset($data['currency']);
        Http::fake(['*' => InvoiceTestData::ollamaResponse($data)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response format');

        app(AiInvoiceExtractor::class)->extract('Invoice text');
    }

    public function test_rejects_wrong_field_type(): void
    {
        Http::fake(['*' => InvoiceTestData::ollamaResponse(
            InvoiceTestData::valid(['currency' => 123])
        )]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid field type');

        app(AiInvoiceExtractor::class)->extract('Invoice text');
    }

    public function test_rejects_missing_message_content(): void
    {
        Http::fake(['*' => Http::response(['message' => []])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid response format');

        app(AiInvoiceExtractor::class)->extract('Invoice text');
    }

    public function test_reports_ollama_http_failure(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Unavailable'], 503)]);
        $this->expectException(RequestException::class);

        app(AiInvoiceExtractor::class)->extract('Invoice text');
    }

    public function test_reports_ollama_connection_failure(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->expectException(ConnectionException::class);

        app(AiInvoiceExtractor::class)->extract('Invoice text');
    }

    public function test_rejects_empty_input_without_network_request(): void
    {
        Http::fake();
        try {
            app(AiInvoiceExtractor::class)->extract('  ');
            $this->fail('Expected empty input to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertSame('Invoice text is empty', $e->getMessage());
        }
        Http::assertNothingSent();
    }
}
