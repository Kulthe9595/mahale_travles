<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('registration_no', 20)->unique();
            $table->string('model', 100);
            $table->string('type', 30)->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->decimal('rate_per_km', 12, 2)->default(0);
            $table->decimal('rate_per_day', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
