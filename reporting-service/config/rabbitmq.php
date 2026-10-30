<?php

return [
    'host' => env('RABBITMQ_HOST'),
    'port' => (int) env('RABBITMQ_PORT', 5673),
    'user' => env('RABBITMQ_USER'),
    'password' => env('RABBITMQ_PASSWORD'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => 'rantauan.events',
    'queue' => 'reporting.penjualan',
];
