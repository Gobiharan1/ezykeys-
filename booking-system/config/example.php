<?php
// Copy to local.php on your server. Never upload your preview database or share credentials.
return [
    'dsn' => 'mysql:host=localhost;dbname=YOUR_BOOKING_DATABASE;charset=utf8mb4',
    'username' => 'YOUR_DATABASE_USER',
    'password' => 'YOUR_DATABASE_PASSWORD',
    'timezone' => 'Australia/Melbourne',
    'preview' => false,
    'session_name' => 'ezy_booking',
];
