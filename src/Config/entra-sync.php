<?php

use App\Models\User;

return [
    'tenant_id' => env('ENTRA_TENANT_ID'),
    'client_id' => env('ENTRA_CLIENT_ID'),
    'client_secret' => env('ENTRA_CLIENT_SECRET'),
    'user_model' => env('ENTRA_USER_MODEL', User::class),

    // Only sync users whose email ends with these domains (empty = sync all)
    'allowed_domains' => array_filter(explode(',', env('ENTRA_ALLOWED_DOMAINS', ''))),

    // Fields to sync from Graph API to local DB
    'field_map' => [
        'microsoft_id' => 'id',
        'name' => 'displayName',
        'email' => 'mail',
        'department' => 'department',
        'job_title' => 'jobTitle',
        'phone' => 'mobilePhone',
    ],
];
