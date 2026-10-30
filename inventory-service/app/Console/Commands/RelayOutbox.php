<?php

namespace App\Console\Commands;

use App\Messaging\RabbitPublisher;
use App\Models\Outbox;
use Illuminate\Console\Command;
use Throwable;

class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay {--terus} {--jeda=2}';

    protected $description = 'Mengirim pesan Outbox ke RabbitMQ';

    public function handle(RabbitPublisher $publisher): int
    {
        do {
            $pesanTertunda = Outbox::whereNull('dikirim_pada')
                ->orderBy('id')
                ->limit(100)
                ->get();

            $jumlah = 0;

            foreach ($pesanTertunda as $pesan) {
                try {
                    $publisher->publish(
                        $pesan->routing_key,
                        $pesan->payload,
                        $pesan->message_id,
                        $pesan->jenis,
                    );
                } catch (Throwable $e) {
                    $this->warn(
                        'Pengiriman gagal ('.class_basename($e).'). '
                        .'Pesan tetap tertunda; putaran dihentikan.'
                    );

                    break;
                }

                $pesan->update(['dikirim_pada' => now()]);
                $jumlah++;
            }

            if ($jumlah > 0) {
                $this->info("{$jumlah} pesan terkirim.");
            }

            if ($this->option('terus')) {
                sleep(max(1, (int) $this->option('jeda')));
            }
        } while ($this->option('terus'));

        return self::SUCCESS;
    }
}
