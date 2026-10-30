<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class InvoiceApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_invoice_cannot_be_approved(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::factory()->create());

        $data = [
            'supplier_name' => 'ZaanTech Diensten B.V.',
            'invoice_number' => 'TEST-001',
            'invoice_date' => '2026-10-23',
            'subtotal_ex_vat' => '200.00',
            'vat_amount' => '42.00',
            'total_in_vat' => '250.00',
            'currency' => 'EUR',
        ];

        $invoice = Invoice::create([
            'supplier_name_normalized' => 'zaantech diensten b.v.',
            'invoice_number' => 'TEST-001',
            'original_ai_data' => $data,
            'current_data' => $data,
            'validation_errors' => [],
            'status' => InvoiceStatus::NeedsAttention,
            'model_name' => 'test-model',
            'processing_time_ms' => 100,
        ]);

        // Invalid invoices should not show the approve action.
        Livewire::test(EditInvoice::class, [
            'record' => $invoice->getRouteKey(),
        ])
            ->assertActionHidden('approve');

        // Simulate an outdated or incorrect stored status.
        $invoice->update([
            'status' => InvoiceStatus::ReadyForReview,
        ]);

        // Approval must still revalidate the actual invoice data.
        Livewire::test(EditInvoice::class, [
            'record' => $invoice->getRouteKey(),
        ])
            ->assertActionVisible('approve')
            ->callAction('approve')
            ->assertNotified('Invoice cannot be approved');

        $invoice->refresh();

        $this->assertSame(
            InvoiceStatus::NeedsAttention,
            $invoice->status
        );

        $this->assertContains(
            'Subtotal + VAT does not match total.',
            $invoice->validation_errors
        );
    }


    public function test_valid_invoice_can_be_approved(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::factory()->create());

        $originalData = [
            'supplier_name' => 'ZaanTech Diensten B.V.',
            'invoice_number' => 'AI-ORIGINAL',
            'invoice_date' => '2026-10-23',
            'subtotal_ex_vat' => '200.00',
            'vat_amount' => '42.00',
            'total_in_vat' => '242.00',
            'currency' => 'EUR',
        ];

        // Simulate a manual correction.
        $correctedData = [
            ...$originalData,
            'invoice_number' => 'TEST-002',
        ];

        $invoice = Invoice::create([
            'supplier_name_normalized' => 'zaantech diensten b.v.',
            'invoice_number' => 'TEST-002',
            'original_ai_data' => $originalData,
            'current_data' => $correctedData,
            'validation_errors' => [],
            'status' => InvoiceStatus::ReadyForReview,
            'model_name' => 'test-model',
            'processing_time_ms' => 100,
        ]);

        Livewire::test(EditInvoice::class, [
            'record' => $invoice->getRouteKey(),
        ])
            ->callAction('approve')
            ->assertNotified('Invoice approved');

        $invoice->refresh();

        $this->assertSame(
            InvoiceStatus::Approved,
            $invoice->status
        );

        $this->assertSame([], $invoice->validation_errors);

        // Original AI extraction must remain untouched.
        $this->assertSame(
            $originalData,
            $invoice->original_ai_data
        );

        $this->assertSame(
            $correctedData,
            $invoice->current_data
        );


        $this->get(
            \App\Filament\Resources\Invoices\InvoiceResource::getUrl(
                'edit',
                ['record' => $invoice->getKey()]
            )
        )->assertForbidden();
    }
}
