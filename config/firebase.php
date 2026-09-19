<?php

return [
    'enabled' => (bool) env('FIREBASE_ENABLED', false),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'web_api_key' => env('FIREBASE_WEB_API_KEY'),
    'credentials' => env('FIREBASE_CREDENTIALS'),
];
