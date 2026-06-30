<?php
$env = [
    // SERVER & AUTH CONFIG
    'JWT_SECRET' => 'SmartMedia_Admin_Secure_Key_9988776655',
    'CLIENT_URL' => 'http://localhost:4200',
    'DB_HOST' => 'localhost', 
    'DB_USER' => 'root',      
    'DB_PASS' => '',           
    'DB_NAME' => 'sms_db',     
    'DB_PORT' => '3306',

    /*
    // ====================================================================
    -- ⚠️ PRODUCTION DATABASE CONFIGURATION (HOSTINGER MYSQL - COMMENTED FOR NOW)
    // ====================================================================
    'DB_HOST' => '127.0.0.1', 
    'DB_USER' => 'u880434361_SMS_db',
    'DB_PASS' => 'SMSKiransir@123456',
    'DB_NAME' => 'u880434361_SMS_db',
    'DB_PORT' => '3306',
    */

    // CLOUDINARY CONFIGURATION
    'CLOUD_NAME' => 'dr4vk9nso',
    'CLOUD_API_KEY' => '554582998586725',
    'CLOUD_API_SECRET' => 'B3LQGv-9m0sVsKcu9RlncJRycbs',

    // SMTP EMAIL CONFIGURATION (GMAIL SECURE ENGINE)
    'EMAIL_HOST' => 'smtp.gmail.com',
    'EMAIL_PORT' => 465,
    'EMAIL_USER' => 'meherharshal924@gmail.com',
    'EMAIL_PASS' => 'axxu hqyt vaxj hihz', 
    'ADMIN_EMAIL' => 'meherharshal924@gmail.com'
];

return $env;
?>