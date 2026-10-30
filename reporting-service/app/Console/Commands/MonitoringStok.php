<?php

namespace App\Console\Commands;

use App\Reporting\PesanTidakValid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JsonException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class MonitoringStok extends Command
{
    protected $signature = 'monitoring:stok';

    protected $description = 'Subscriber demonstrasi perubahan stok';

    public function handle(): int
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
            $channel->queue_declare('monitoring.stok', true);
            $channel->basic_qos(0, 1, false);

            $channel->basic_consume(
                'monitoring.stok',
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $pesan) {
                    try {
                        $event = json_decode(
                            $pesan->getBody(),
                            true,
                            512,
                            JSON_THROW_ON_ERROR,
                        );

                        $id = $pesan->has('message_id')
                            ? (string) $pesan->get('message_id')
                            : '';

                        if (! is_array($event)
                            || array_is_list($event)
                            || ! Str::isUuid($id)
                            || ! $pesan->has('type')
                            || $pesan->get('type') !== 'StokDisesuaikan') {
                            throw new PesanTidakValid(
                                'Pesan tidak sesuai kontrak.'
                            );
                        }

                        $validator = Validator::make($event, [
                            'schema_version' => ['required', 'integer', 'in:1'],
                            'operation_id' => ['required', 'uuid'],
                            'sku_internal' => ['required', 'string', 'max:7'],
                            'perubahan' => ['required', 'integer', 'not_in:0'],
                            'alasan' => ['required', 'in:penjualan,restok,koreksi'],
                            'stok_sesudah' => ['required', 'integer', 'min:0'],
                            'terjadi_pada' => [
                                'required',
                                'date_format:Y-m-d\TH:i:sP',
                            ],
                        ]);

                        if ($validator->fails()) {
                            throw new PesanTidakValid(
                                'Payload tidak sesuai kontrak.'
                            );
                        }

                        $pesan->ack();

                        $this->line('Monitoring menerima event '.$id);
                    } catch (PesanTidakValid|JsonException $e) {
                        $pesan->nack(false);

                        $this->warn(
                            'Pesan monitoring tidak valid dibuang tanpa requeue.'
                        );
                    } catch (Throwable $e) {
                        $ulang = ! $pesan->isRedelivered();
                        $pesan->nack($ulang);

                        $this->warn(
                            $ulang
                                ? 'Gangguan monitoring; pesan dicoba ulang.'
                                : 'Retry gagal; salinan monitoring dibuang.'
                        );
                    }
                },
            );

            $this->info(
                'Monitoring menunggu event. Tekan Ctrl+C untuk berhenti.'
            );

            $channel->consume();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(
                'Monitoring berhenti ('.class_basename($e).').'
            );

            return self::FAILURE;
        } finally {
            try {
                $channel?->close();
            } catch (Throwable) {
            }

            try {
                $connection?->close();
            } catch (Throwable) {
            }
        }
    }
}
