<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('car_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('pickup', 200)->nullable();
            $table->string('drop', 200)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('trip_type', 20);
            $table->unsignedInteger('start_km')->nullable();
            $table->unsignedInteger('end_km')->nullable();
            $table->json('billing_inputs');
            $table->string('status', 20)->default('booked')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
