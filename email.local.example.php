<?php
// Optional local development settings. Environment variables override these values.
// For hosting, use the environment variables documented in EMAIL_OTP.md.
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-sender@gmail.com',
    'password' => '', // Enter your App Password locally, without spaces.
    'from' => 'your-sender@gmail.com',
    'from_name' => 'OneLara',
];
