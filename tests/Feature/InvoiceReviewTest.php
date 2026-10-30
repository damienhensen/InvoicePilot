<?php

namespace Tests\Feature;

use App\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\InvoiceTestData;
use Tests\TestCase;

final class InvoiceReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_manual_correction_revalidates_and_preserves_ai_output(): void
    {
        $invoice = InvoiceTestData::create([
            'invoice_number' => 'AI-ORIGINAL',
        ]);
        $original = $invoice->original_ai_data;
        $corrected = InvoiceTestData::valid(['invoice_number' => 'CORRECTED-001']);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
            ->fillForm(['current_data' => $corrected])
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::ReadyForReview, $invoice->status);
        $actual = $invoice->current_data;
        ksort($corrected);
        ksort($actual);
        $this->assertSame($corrected, $actual);
        $this->assertSame($original, $invoice->original_ai_data);
        $this->assertSame('CORRECTED-001', $invoice->invoice_number);
    }

    public function test_invalid_correction_is_saved_with_warning(): void
    {
        $invoice = InvoiceTestData::create();
        $bad = InvoiceTestData::valid(['total_in_vat' => '250.00']);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
            ->fillForm(['current_data' => $bad])
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::NeedsAttention, $invoice->status);
        $this->assertSame('250.00', $invoice->current_data['total_in_vat']);
        $this->assertContains('Subtotal + VAT does not match total.', $invoice->validation_errors);
        $this->assertSame('242.00', $invoice->original_ai_data['total_in_vat']);
    }

    public function test_edit_to_existing_invoice_number_sets_duplicate_warning(): void
    {
        InvoiceTestData::create(['invoice_number' => 'ALREADY-USED']);
        $invoice = InvoiceTestData::create(['invoice_number' => 'UNIQUE-NUMBER']);
        $edited = InvoiceTestData::valid(['invoice_number' => 'ALREADY-USED']);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
            ->fillForm(['current_data' => $edited])
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::NeedsAttention, $invoice->status);
        $this->assertContains('Possible duplicate invoice.', $invoice->validation_errors);
    }
}
