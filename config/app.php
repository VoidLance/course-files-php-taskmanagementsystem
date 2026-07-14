<?php

declare(strict_types=1);

return [
    'app' => [
        // Frontend/API metadata and token defaults.
        'name' => 'Task Management System',
        'base_url' => '/TaskManagementSystem/public',
        'jwt_secret' => 'please-change-this-super-secret-key',
        'jwt_ttl' => 7200,
        'timezone' => 'UTC',
    ],
    'db' => [
        // Local DB credentials (update if your machine differs).
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'ecommerce',
        'username' => 'ecom_user',
        'password' => 'EcomPass2024',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        // Dev-only mail sink: verification/reset tokens are logged to file.
        'from' => 'noreply@task.local',
        'log_file' => STORAGE_PATH . '/logs/mail.log',
    ],
];
