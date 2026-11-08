<?php
declare(strict_types=1);

return [
    'host' => getenv('LIBRARY_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('LIBRARY_DB_PORT') ?: 3306),
    'user' => getenv('LIBRARY_DB_USER') ?: 'root',
    'password' => getenv('LIBRARY_DB_PASSWORD') ?: '',
    'database' => getenv('LIBRARY_DB_NAME') ?: 'database_perpustakaan',
    'fine_per_day' => 10000,
    'loan_days' => 7,
];
