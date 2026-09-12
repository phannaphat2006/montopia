<?php

return [
    'project_file_max_kb' => (int) env('PROJECT_FILE_MAX_KB', 10240),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
];
