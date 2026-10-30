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

final class InvoiceApprovalDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_cannot_be_approved_even_with_stale_valid_status(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());

        InvoiceTestData::create();
        $duplicate = InvoiceTestData::create([], [
            'validation_errors' => [],
            'status' => InvoiceStatus::ReadyForReview,
        ]);

        Livewire::test(EditInvoice::class, ['record' => $duplicate->getRouteKey()])
            ->callAction('approve')
            ->assertNotified('Invoice cannot be approved');

        $duplicate->refresh();
        $this->assertSame(InvoiceStatus::NeedsAttention, $duplicate->status);
        $this->assertContains('Possible duplicate invoice.', $duplicate->validation_errors);
    }
}
