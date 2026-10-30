<?php

namespace App\Models;

use App\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'supplier_name_normalized',
        'invoice_number',
        'original_ai_data',
        'current_data',
        'validation_errors',
        'status',
        'model_name',
        'processing_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'original_ai_data' => 'array',
            'current_data' => 'array',
            'validation_errors' => 'array',
            'processing_time_ms' => 'integer',
            'status' => InvoiceStatus::class,
        ];
    }
}
