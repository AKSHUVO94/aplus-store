<?php
return [
    'app_name'    => 'AK',
    'app_url'     => 'http://ak.test',
    'app_debug'   => true,
    'timezone'    => 'Asia/Dhaka',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'ak_store',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'session_name' => 'ak_store_session',
    'paths' => [
        'root'  => dirname(__DIR__, 2),
        'views' => dirname(__DIR__) . '/views',
    ],
    'currency_symbol' => '৳',
];
