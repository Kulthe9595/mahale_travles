<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Mahale Tours and Travels');
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('state')->default('Maharashtra');
            $table->string('sac_code', 10)->default('996601');
            $table->decimal('gst_rate', 5, 2)->default(5);
            $table->unsignedInteger('min_km_per_day')->default(0);
            $table->string('invoice_prefix', 10)->default('MTT');
            $table->text('bank_details')->nullable();
            $table->string('upi_id')->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
