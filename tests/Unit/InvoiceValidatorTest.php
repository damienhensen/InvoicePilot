<?php

namespace Tests\Unit;

use App\Services\InvoiceValidator;
use PHPUnit\Framework\TestCase;

class InvoiceValidatorTest extends TestCase
{
    private InvoiceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new InvoiceValidator();
    }

    private function validInvoice(): array
    {
        return [
            'supplier_name' => 'ZaanTech Diensten B.V.',
            'invoice_number' => 'ZTD-2026-041',
            'invoice_date' => '2026-10-23',
            'subtotal_ex_vat' => '200.00',
            'vat_amount' => '42.00',
            'total_in_vat' => '242.00',
            'currency' => 'EUR',
        ];
    }

    public function test_valid_invoice_passes(): void
    {
        $errors = $this->validator->validate(
            $this->validInvoice()
        );

        $this->assertSame([], $errors);
    }

    public function test_incorrect_total_fails(): void
    {
        $invoice = $this->validInvoice();
        $invoice['total_in_vat'] = '250.00';

        $errors = $this->validator->validate($invoice);

        $this->assertContains(
            'Subtotal + VAT does not match total.',
            $errors
        );
    }

    public function test_missing_invoice_number_fails(): void
    {
        $invoice = $this->validInvoice();
        $invoice['invoice_number'] = null;

        $errors = $this->validator->validate($invoice);

        $this->assertContains(
            'Invoice number is missing.',
            $errors
        );
    }

    public function test_invalid_date_fails(): void
    {
        $invoice = $this->validInvoice();
        $invoice['invoice_date'] = '2026-02-30';

        $errors = $this->validator->validate($invoice);

        $this->assertContains(
            'Invalid invoice date.',
            $errors
        );
    }

    public function test_two_cent_rounding_difference_is_allowed(): void
    {
        $invoice = $this->validInvoice();
        $invoice['total_in_vat'] = '242.02';

        $errors = $this->validator->validate($invoice);

        $this->assertSame([], $errors);
    }
}
