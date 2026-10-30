<?php

namespace App\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class RabbitPublisher
{
    public function publish(
        string $routingKey,
        array $data,
        string $messageId,
        string $type,
    ): void {
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

            $diakui = false;
            $ditolak = false;
            $dikembalikan = false;

            $channel->set_ack_handler(function () use (&$diakui) {
                $diakui = true;
            });

            $channel->set_nack_handler(function () use (&$ditolak) {
                $ditolak = true;
            });

            $channel->set_return_listener(
                function () use (&$dikembalikan) {
                    $dikembalikan = true;
                }
            );

            $channel->confirm_select();

            $message = new AMQPMessage(
                json_encode($data, JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $messageId,
                    'type' => $type,
                    'timestamp' => time(),
                ],
            );

            $channel->basic_publish(
                $message,
                $c['exchange'],
                $routingKey,
                true,
            );

            $channel->wait_for_pending_acks_returns(5);

            if (! $diakui || $ditolak || $dikembalikan) {
                throw new RuntimeException(
                    'Pesan belum dikonfirmasi atau tidak memiliki tujuan.'
                );
            }
        } finally {
            try {
                $channel?->close();
            } finally {
                $connection->close();
            }
        }
    }
}
