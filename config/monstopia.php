<?php

return [
    'business_timezone' => env('MONSTOPIA_BUSINESS_TIMEZONE', 'Asia/Bangkok'),
    'project_file_max_kb' => (int) env('PROJECT_FILE_MAX_KB', 10240),
    'project_file_disk' => env('PROJECT_FILE_DISK', env('FILESYSTEM_DISK', 'local')),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'backup_external_directory' => env('BACKUP_EXTERNAL_DIRECTORY'),
    'restore_host' => env('MONSTOPIA_RESTORE_HOST'),
    'restore_port' => env('MONSTOPIA_RESTORE_PORT'),
    'restore_username' => env('MONSTOPIA_RESTORE_USERNAME'),
    'restore_password' => env('MONSTOPIA_RESTORE_PASSWORD'),
];
