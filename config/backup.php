<?php

return [
    'status_path' => storage_path('app/private/mysql-backup-status.json'),
    'enabled' => env('BACKUP_ENABLED', true),
    'interval_hours' => (int) env('BACKUP_INTERVAL_HOURS', 1),
    'retention_hours' => (int) env('BACKUP_RETENTION_HOURS', 168),
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
    'gzip_binary' => env('BACKUP_GZIP_BINARY', 'gzip'),
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 1800),
];
