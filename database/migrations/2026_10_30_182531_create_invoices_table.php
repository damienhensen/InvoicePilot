<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Used for duplicate detection.
            $table->string('supplier_name_normalized')->nullable();
            $table->string('invoice_number')->nullable();

            // Preserve the original model output.
            $table->json('original_ai_data');

            // Initially identical; later editable by the user.
            $table->json('current_data');

            $table->json('validation_errors');

            $table->string('status');
            $table->string('model_name');
            $table->unsignedInteger('processing_time_ms')->nullable();

            $table->timestamps();

            $table->index([
                'supplier_name_normalized',
                'invoice_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
