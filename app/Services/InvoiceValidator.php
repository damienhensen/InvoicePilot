<?php

namespace App\Services;

use DateTimeImmutable;

final class InvoiceValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        $required = [
            'supplier_name' => 'Supplier name',
            'invoice_number' => 'Invoice number',
            'invoice_date' => 'Invoice date',
            'subtotal_ex_vat' => 'Subtotal',
            'vat_amount' => 'VAT amount',
            'total_in_vat' => 'Total',
            'currency' => 'Currency',
        ];

        foreach ($required as $field => $label) {
            $value = $data[$field] ?? null;

            if (!is_string($value) || trim($value) === '') {
                $errors[] = "{$label} is missing.";
            }
        }

        $date = $data['invoice_date'] ?? null;

        if (is_string($date) && trim($date) !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                $errors[] = 'Invalid invoice date.';
            }
        }

        $amounts = [];

        foreach (['subtotal_ex_vat', 'vat_amount', 'total_in_vat'] as $field) {
            $value = $data[$field] ?? null;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            if (!preg_match(
                '/\A(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/',
                $value
            )) {
                $errors[] = "Invalid monetary amount: {$field}.";
                continue;
            }

            [$euros, $cents] = explode('.', $value);

            $amounts[$field] = (int) $euros * 100 + (int) $cents;
        }

        if (count($amounts) === 3) {
            $difference = abs(
                $amounts['subtotal_ex_vat']
                    + $amounts['vat_amount']
                    - $amounts['total_in_vat']
            );

            if ($difference > 2) {
                $errors[] = 'Subtotal + VAT does not match total.';
            }
        }

        $currency = $data['currency'] ?? null;

        if (
            is_string($currency) && trim($currency) !== ''
            && $currency !== 'EUR'
        ) {
            $errors[] = 'Only EUR invoices are supported.';
        }

        return $errors;
    }
}
