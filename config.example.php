<?php
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'kazimates',
    'db_user' => 'root',
    'db_pass' => '',
    'app_name' => 'KaziMates',
    // Password-reset email (use ONE of the two). These can also be set as environment variables, e.g. RESEND_API_KEY.
    'app_url' => '',            // e.g. https://your-site.up.railway.app (used in reset links)
    'mail_from' => '',          // e.g. KaziMates <no-reply@yourdomain.com>
    'resend_api_key' => '',     // Resend HTTPS API (recommended)
    'smtp_host' => '', 'smtp_port' => 587, 'smtp_user' => '', 'smtp_password' => '', 'smtp_secure' => 'tls', // or SMTP
];
