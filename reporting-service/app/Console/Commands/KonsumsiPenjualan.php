<?php

namespace App\Console\Commands;

use App\Reporting\CatatPenjualan;
use App\Reporting\PesanTidakValid;
use Illuminate\Console\Command;
use JsonException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class KonsumsiPenjualan extends Command
{
    protected $signature = 'reporting:konsumsi';

    protected $description = 'Mengonsumsi event penjualan RabbitMQ';

    public function handle(CatatPenjualan $catat): int
    {
        $connection = null;
        $channel = null;

        try {
            $c = config('rabbitmq');

            $connection = new AMQPStreamConnection(
                $c['host'],
                $c['port'],
                $c['user'],
                $c['password'],
                $c['vhost'],
            );

            $channel = $connection->channel();

            // Pastikan queue yang disiapkan Inventory tersedia.
            $channel->queue_declare($c['queue'], true);

            // Proses satu pesan yang belum di-ack pada satu waktu.
            $channel->basic_qos(0, 1, false);

            $channel->basic_consume(
                $c['queue'],
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $pesan) use ($catat) {
                    try {
                        if (! $pesan->has('type')
                            || $pesan->get('type') !== 'StokDisesuaikan') {
                            throw new PesanTidakValid(
                                'Jenis event tidak sesuai kontrak.'
                            );
                        }

                        $event = json_decode(
                            $pesan->getBody(),
                            true,
                            512,
                            JSON_THROW_ON_ERROR,
                        );

                        if (! is_array($event) || array_is_list($event)) {
                            throw new PesanTidakValid(
                                'Payload harus berupa objek JSON.'
                            );
                        }

                        $messageId = $pesan->has('message_id')
                            ? (string) $pesan->get('message_id')
                            : '';

                        $baru = $catat($event, $messageId);

                        $pesan->ack();

                        $this->line(
                            ($baru ? 'Dicatat: ' : 'Dilewati: ')
                            .'event '.$messageId
                        );
                    } catch (PesanTidakValid|JsonException $e) {
                        $pesan->nack(false);

                        $this->warn(
                            'Pesan tidak valid dialihkan ke DLQ.'
                        );
                    } catch (Throwable $e) {
                        $ulang = ! $pesan->isRedelivered();

                        $pesan->nack($ulang);

                        $this->warn(
                            $ulang
                                ? 'Pemrosesan gagal; pesan dikembalikan untuk retry.'
                                : 'Retry gagal; pesan dialihkan ke DLQ.'
                        );
                    }
                },
            );

            $this->info(
                'Menunggu event penjualan. Tekan Ctrl+C untuk berhenti.'
            );

            $channel->consume();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(
                'Consumer berhenti ('.class_basename($e).').'
            );

            return self::FAILURE;
        } finally {
            try {
                $channel?->close();
            } catch (Throwable) {
                // Koneksi mungkin sudah terputus.
            }

            try {
                $connection?->close();
            } catch (Throwable) {
                // Koneksi mungkin sudah terputus.
            }
        }
    }
}
