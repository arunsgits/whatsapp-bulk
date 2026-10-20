<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('route')->nullable();
            $table->string('icon')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
        
        // Seed default modules
        $defaultModules = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'route' => '/dashboard', 'icon' => 'LayoutGrid', 'status' => 'active'],
            ['name' => 'Contacts', 'slug' => 'contacts', 'route' => '/contacts', 'icon' => 'Users', 'status' => 'active'],
            ['name' => 'Media Library', 'slug' => 'media_library', 'route' => '/media-library', 'icon' => 'Image', 'status' => 'active'],
            ['name' => 'Templates', 'slug' => 'templates', 'route' => '/templates', 'icon' => 'FileText', 'status' => 'active'],
            ['name' => 'Bulk Send', 'slug' => 'bulk_send', 'route' => '/bulk-send', 'icon' => 'Send', 'status' => 'active'],
            ['name' => 'Campaigns', 'slug' => 'campaigns', 'route' => '/campaigns', 'icon' => 'Megaphone', 'status' => 'active'],
            ['name' => 'Conversations', 'slug' => 'conversations', 'route' => '/conversations', 'icon' => 'MessageSquare', 'status' => 'active'],
            ['name' => 'Reports', 'slug' => 'reports', 'route' => '/reports', 'icon' => 'BarChart2', 'status' => 'active'],
            ['name' => 'Settings', 'slug' => 'settings', 'route' => '/settings', 'icon' => 'Settings', 'status' => 'active'],
            ['name' => 'Billing', 'slug' => 'billing', 'route' => '/billing', 'icon' => 'CreditCard', 'status' => 'active'],
        ];

        DB::table('modules')->insert($defaultModules);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
