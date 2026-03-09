<?php

$allowedEmailDomains = array_values(array_filter(array_map(
    static fn (string $domain): string => strtolower(trim($domain)),
    explode(',', (string) env('LNU_ALLOWED_EMAIL_DOMAINS', 'lnu.edu.ph'))
)));

$allowedStudentIdPrefixes = array_values(array_filter(array_map(
    static fn (string $prefix): string => trim($prefix),
    explode(',', (string) env('LNU_ALLOWED_STUDENT_ID_PREFIXES', '210,220,230,240,250,260,270,280'))
)));

return [
    'allowed_email_domains' => $allowedEmailDomains,
    'allowed_student_id_prefixes' => $allowedStudentIdPrefixes,
    'enforce_email_student_id_match' => filter_var(env('LNU_ENFORCE_EMAIL_STUDENT_ID_MATCH', true), FILTER_VALIDATE_BOOL),
    'student_id_prefix_length' => (int) env('LNU_STUDENT_ID_PREFIX_LENGTH', 3),
    'password_uncompromised' => filter_var(env('LNU_PASSWORD_UNCOMPROMISED', false), FILTER_VALIDATE_BOOL),
    'email_otp_expires_minutes' => (int) env('LNU_EMAIL_OTP_EXPIRES_MINUTES', 10),
    'admin_web_username' => (string) env('LNU_ADMIN_WEB_USERNAME', 'admin'),
    'admin_seed_student_id' => (string) env('LNU_ADMIN_STUDENT_ID', '2303838'),
    'admin_seed_password' => (string) env('LNU_ADMIN_PASSWORD', 'admin123'),
];
