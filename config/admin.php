<?php

return [
    'username' => env('ADMIN_WEB_USERNAME', 'admin'),
    'password_hash' => env('ADMIN_WEB_PASSWORD_HASH'),
    'session_key' => 'automind_admin_authenticated',
];
