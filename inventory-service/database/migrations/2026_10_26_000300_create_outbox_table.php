<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('jenis', 60);
            $table->string('routing_key', 60);
            $table->json('payload');
            $table->timestamp('dikirim_pada')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
