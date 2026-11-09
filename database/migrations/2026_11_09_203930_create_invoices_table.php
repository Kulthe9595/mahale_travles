<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->string('invoice_no', 40)->unique();
            $table->string('financial_year', 7)->index();
            $table->date('invoice_date')->index();
            $table->boolean('gst_enabled')->default(false);
            $table->string('tax_type', 20)->default('none');
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('round_off', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->string('share_token', 64)->unique();
            $table->boolean('share_enabled')->default(true);
            $table->timestamp('locked_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('revision_of_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
