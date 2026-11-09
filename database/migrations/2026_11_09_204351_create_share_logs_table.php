<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('recipient', 150)->nullable();
            $table->timestamp('shared_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_logs');
    }
};
