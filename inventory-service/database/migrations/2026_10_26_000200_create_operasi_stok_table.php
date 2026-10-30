<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_stok', function (Blueprint $table) {
            $table->uuid('operation_id')->primary();
            $table->string('sku', 7)->index();
            $table->char('payload_hash', 64);
            $table->json('hasil');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_stok');
    }
};
