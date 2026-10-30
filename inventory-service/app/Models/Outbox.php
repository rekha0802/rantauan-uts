<?php

namespace App\Models;

use App\Events\StokDisesuaikan;
use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    protected $table = 'outbox';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'jenis',
        'routing_key',
        'payload',
        'dikirim_pada',
    ];

    protected $casts = [
        'payload' => 'array',
        'dikirim_pada' => 'datetime',
    ];

    // Panggil di dalam transaksi perubahan stok.
    public static function catat(StokDisesuaikan $event): self
    {
        return static::create([
            'message_id' => $event->id,
            'jenis' => 'StokDisesuaikan',
            'routing_key' => "stok.{$event->alasan}.v1",
            'payload' => $event->payload(),
        ]);
    }
}
