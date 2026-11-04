<?php

return [
    'driver' => 'argon2id',
    'bcrypt' => ['rounds' => (int) env('BCRYPT_ROUNDS', 12), 'verify' => true, 'limit' => 72],
    'argon' => [
        'memory' => max(19456, (int) env('ARGON_MEMORY', 65536)),
        'time' => max(2, (int) env('ARGON_TIME', 4)),
        'threads' => 1,
        'verify' => true,
    ],
    'rehash_on_login' => true,
];
