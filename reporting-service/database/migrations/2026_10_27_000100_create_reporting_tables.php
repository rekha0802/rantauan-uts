<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox', function (Blueprint $table) {
            $table->uuid('message_id')->primary();
            $table->char('payload_hash', 64);
            $table->timestamp('diterima_pada')->useCurrent();
        });

        Schema::create('jurnal_penjualan', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->uuid('operation_id')->unique();
            $table->string('sku_internal', 7)->index();
            $table->unsignedBigInteger('jumlah');
            $table->dateTime('terjadi_pada');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_penjualan');
        Schema::dropIfExists('inbox');
    }
};
