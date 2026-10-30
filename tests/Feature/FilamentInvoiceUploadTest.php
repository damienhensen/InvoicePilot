<?php

namespace Tests\Feature;

use App\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\InvoiceTestData;
use Tests\TestCase;

final class FilamentInvoiceUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        Http::preventStrayRequests();
    }

    public function test_valid_pdf_upload_creates_invoice_with_mocked_ai(): void
    {
        Http::fake(['*' => InvoiceTestData::ollamaResponse(InvoiceTestData::valid())]);
        $bytes = file_get_contents(dirname(__DIR__) . '/Fixtures/valid-invoice.pdf');
        $this->assertNotFalse($bytes);

        $file = UploadedFile::fake()->createWithContent('test-invoice.pdf', $bytes);

        Livewire::test(ListInvoices::class)
            ->callAction('uploadInvoice', data: ['invoice' => $file])
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(InvoiceStatus::ReadyForReview, Invoice::firstOrFail()->status);
        Http::assertSentCount(1);
    }

    public function test_text_file_upload_is_rejected_before_ai_processing(): void
    {
        Http::fake();
        $file = UploadedFile::fake()->createWithContent('invoice.txt', 'Not a PDF');

        Livewire::test(ListInvoices::class)
            ->callAction('uploadInvoice', data: ['invoice' => $file])
            ->assertHasFormErrors();

        $this->assertDatabaseCount('invoices', 0);
        Http::assertNothingSent();
    }

    public function test_oversized_upload_is_rejected_before_ai_processing(): void
    {
        Http::fake();
        $file = UploadedFile::fake()->create('invoice.pdf', 3073, 'application/pdf');

        Livewire::test(ListInvoices::class)
            ->callAction('uploadInvoice', data: ['invoice' => $file])
            ->assertHasFormErrors();

        $this->assertDatabaseCount('invoices', 0);
        Http::assertNothingSent();
    }
}
