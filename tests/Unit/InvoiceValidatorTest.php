<?php

namespace Tests\Unit;

use App\Services\InvoiceValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\InvoiceTestData;

final class InvoiceValidatorTest extends TestCase
{
    private InvoiceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InvoiceValidator();
    }

    public function test_valid_invoice_passes(): void
    {
        $this->assertSame([], $this->validator->validate(InvoiceTestData::valid()));
    }

    public function test_incorrect_total_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'total_in_vat' => '250.00',
        ]));

        $this->assertContains('Subtotal + VAT does not match total.', $errors);
    }

    public function test_missing_invoice_number_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'invoice_number' => null,
        ]));

        $this->assertContains('Invoice number is missing.', $errors);
    }

    public function test_invalid_calendar_date_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'invoice_date' => '2026-02-30',
        ]));

        $this->assertContains('Invalid invoice date.', $errors);
    }

    public function test_two_cent_rounding_difference_is_allowed(): void
    {
        $this->assertSame([], $this->validator->validate(InvoiceTestData::valid([
            'total_in_vat' => '242.02',
        ])));
    }

    public function test_three_cent_rounding_difference_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'total_in_vat' => '242.03',
        ]));

        $this->assertContains('Subtotal + VAT does not match total.', $errors);
    }

    public function test_negative_amount_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'subtotal_ex_vat' => '-200.00',
        ]));

        $this->assertContains('Invalid monetary amount: subtotal_ex_vat.', $errors);
    }

    public function test_comma_decimal_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'vat_amount' => '42,00',
        ]));

        $this->assertContains('Invalid monetary amount: vat_amount.', $errors);
    }

    public function test_non_eur_currency_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'currency' => 'USD',
        ]));

        $this->assertContains('Only EUR invoices are supported.', $errors);
    }

    public function test_multiple_missing_fields_are_reported(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'supplier_name' => null,
            'invoice_number' => null,
        ]));

        $this->assertContains('Supplier name is missing.', $errors);
        $this->assertContains('Invoice number is missing.', $errors);
    }

    public function test_amount_above_supported_integer_range_fails(): void
    {
        $errors = $this->validator->validate(InvoiceTestData::valid([
            'subtotal_ex_vat' => '1234567890123.00',
        ]));

        $this->assertContains('Invalid monetary amount: subtotal_ex_vat.', $errors);
    }
}
