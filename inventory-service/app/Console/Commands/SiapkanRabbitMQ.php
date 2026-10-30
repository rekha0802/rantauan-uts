<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class SiapkanRabbitMQ extends Command
{
    protected $signature = 'rabbitmq:siapkan';

    protected $description = 'Menyiapkan exchange, queue, dan binding UTS';

    public function handle(): int
    {
        $c = config('rabbitmq');

        $connection = new AMQPStreamConnection(
            $c['host'],
            $c['port'],
            $c['user'],
            $c['password'],
            $c['vhost'],
        );

        $channel = null;

        try {
            $channel = $connection->channel();

            $channel->exchange_declare(
                $c['exchange'],
                'topic',
                false,
                true,
                false,
            );

            $channel->exchange_declare(
                'rantauan.dlx',
                'fanout',
                false,
                true,
                false,
            );

            $channel->queue_declare(
                'reporting.penjualan.dlq',
                false,
                true,
                false,
                false,
            );

            $channel->queue_bind(
                'reporting.penjualan.dlq',
                'rantauan.dlx',
            );

            $channel->queue_declare(
                'reporting.penjualan',
                false,
                true,
                false,
                false,
                false,
                new AMQPTable([
                    'x-dead-letter-exchange' => 'rantauan.dlx',
                ]),
            );

            $channel->queue_bind(
                'reporting.penjualan',
                $c['exchange'],
                'stok.penjualan.v1',
            );

            $channel->queue_declare(
                'monitoring.stok',
                false,
                true,
                false,
                false,
            );

            $channel->queue_bind(
                'monitoring.stok',
                $c['exchange'],
                'stok.*.v1',
            );

            $this->info('Exchange, queue, dan binding siap.');

            return self::SUCCESS;
        } finally {
            try {
                $channel?->close();
            } finally {
                $connection->close();
            }
        }
    }
}
