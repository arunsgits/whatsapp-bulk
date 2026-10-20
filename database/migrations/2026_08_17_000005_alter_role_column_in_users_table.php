<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify role column to varchar(50) to support super_admin
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'");

        // Seed / Update Super Admin User account
        User::updateOrCreate(
            ['email' => 'admin@zapreach.com'],
            [
                'name' => 'Super Admin',
                'number' => '+919876543210',
                'password' => Hash::make('secretPassword'),
                'role' => 'super_admin',
                'status' => 'active',
                'companyName' => 'ZapReach',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user'");
    }
};
