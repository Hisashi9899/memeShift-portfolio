<?php
return [
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        'api' => ['driver' => 'sanctum', 'provider' => 'users'],
    ],
    'providers' => ['users' => ['driver' => 'eloquent', 'model' => App\Models\User::class]],
    'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60]],
];
