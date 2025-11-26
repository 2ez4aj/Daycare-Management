<?php
return [
    'app_name' => 'Gumamela Daycare Center',
    'app_url' => 'http://localhost/NewDaycare',
    'timezone' => 'Asia/Manila',
    'session_lifetime' => 7200, // 2 hours
    
    'database' => [
        'host' => 'localhost',
        'dbname' => 'gumamela_daycare1',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],
    
    'upload' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'path' => __DIR__ . '/../../uploads'
    ],
    
    'email' => [
        'from' => 'driancalda@gmail.com',    // the Gmail you generated the app password for
        'from_name' => 'Gumamela',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => 'driancalda@gmail.com',
        'smtp_password' => 'fuwv rrip vtpw unsq',  // 16-character code without spaces
        'smtp_secure' => 'tls',
        'smtp_auth' => true,
        'smtp_debug' => 2  // set to 0 after troubleshooting
    ]
];
