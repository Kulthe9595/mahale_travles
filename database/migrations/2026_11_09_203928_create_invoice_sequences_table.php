<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 7);
            $table->string('prefix', 10);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['financial_year', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
