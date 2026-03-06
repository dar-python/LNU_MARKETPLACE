<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE student_verifications MODIFY COLUMN verification_type ENUM('email_otp', 'email_link', 'password_reset_otp') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE student_verifications MODIFY COLUMN verification_type ENUM('email_otp', 'email_link') NOT NULL");
    }
};