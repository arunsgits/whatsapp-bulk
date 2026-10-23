<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Package;
use App\Models\Module;
use App\Models\Settings;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Super Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@zapreach.com'],
            [
                'name' => 'ZapReach Admin',
                'number' => '919876543210',
                'password' => Hash::make('ZapReach@2026!'),
                'role' => 'super_admin',
                'status' => 'active',
                'wallet_enabled' => true,
                'wallet_balance' => 1000.00,
                'companyName' => 'ZapReach Technologies',
                'companyAddress' => 'Tech Park, Silicon Valley, CA / Mumbai, MH',
                'companyEmail' => 'contact@zapreach.com',
                'companyMobile' => '919876543210',
                'permissions' => json_encode(['*']),
            ]
        );

        // 2. Seed Standard Demo User
        $demoUser = User::updateOrCreate(
            ['email' => 'demo@zapreach.com'],
            [
                'name' => 'Demo User',
                'number' => '919876543211',
                'password' => Hash::make('ZapReach@2026!'),
                'role' => 'user',
                'status' => 'active',
                'wallet_enabled' => true,
                'wallet_balance' => 250.00,
                'companyName' => 'Acme Global Commerce',
                'companyAddress' => '100 Business Center, Suite 400',
                'companyEmail' => 'demo@acmeglobal.com',
                'companyMobile' => '919876543211',
                'permissions' => json_encode(['*']),
            ]
        );

        // 3. Seed Default Subscription Packages
        $packages = [
            [
                'packageName' => 'Starter',
                'packageDesc' => 'Perfect for solopreneurs and small businesses getting started with WhatsApp outreach.',
                'day' => 30,
                'msgCount' => 1000,
                'templateCount' => 10,
                'contactCount' => 500,
                'price' => '999',
                'renewal_price' => '999',
                'period' => '/month',
                'popular' => false,
                'color' => 'emerald',
                'iconName' => 'zap',
                'features' => [
                    '1,000 Messages per Month',
                    '10 Approved Message Templates',
                    '500 Contact Storage Limit',
                    'Smart Auto-Reply Engine',
                    'Standard Support Response',
                ],
            ],
            [
                'packageName' => 'Growth',
                'packageDesc' => 'Designed for scaling companies wanting bulk broadcast and AI-driven automation.',
                'day' => 30,
                'msgCount' => 5000,
                'templateCount' => 30,
                'contactCount' => 3000,
                'price' => '2499',
                'renewal_price' => '2499',
                'period' => '/month',
                'popular' => true,
                'color' => 'blue',
                'iconName' => 'rocket',
                'features' => [
                    '5,000 Messages per Month',
                    '30 Approved Message Templates',
                    '3,000 Contact Storage Limit',
                    'Auto-AI Contextual Smart Bot',
                    'Commerce Catalog & Product Showcase',
                    'Priority 24/7 SLA Support',
                ],
            ],
            [
                'packageName' => 'Enterprise',
                'packageDesc' => 'High-capacity broadcast and CRM automation platform for established high-volume enterprises.',
                'day' => 30,
                'msgCount' => 25000,
                'templateCount' => 100,
                'contactCount' => 15000,
                'price' => '6999',
                'renewal_price' => '6999',
                'period' => '/month',
                'popular' => false,
                'color' => 'purple',
                'iconName' => 'crown',
                'features' => [
                    '25,000 Messages per Month',
                    'Unlimited Template Creation',
                    '15,000 Contact Storage Limit',
                    'Full AI Autonomous Bot & Custom Rules',
                    'Multi-Agent Live Chat Inbox',
                    'Dedicated Account Manager & REST Webhooks',
                ],
            ],
        ];

        foreach ($packages as $pkgData) {
            Package::updateOrCreate(
                ['packageName' => $pkgData['packageName']],
                $pkgData
            );
        }

        // 4. Seed Default Admin & Demo Settings
        Settings::updateOrCreate(
            ['userId' => $admin->id],
            [
                'business_name' => 'ZapReach Technologies',
                'business_type' => 'Software & WhatsApp CRM',
                'business_details' => 'ZapReach is an enterprise-grade WhatsApp Business Cloud API broadcast and marketing automation platform.',
                'business_hours' => 'Mon-Sat 09:00 AM - 07:00 PM',
                'auto_ai_enabled' => true,
                'auto_ai_tone' => 'friendly',
                'first_time_message' => 'Hello! Welcome to ZapReach. How may we assist your business today?',
                'is_app_live' => 1,
                'wallet_min_balance' => 200.00,
                'wallet_price_marketing' => 0.85,
                'wallet_price_utility' => 0.35,
                'wallet_price_authentication' => 0.35,
                'batch_size' => 50,
                'batch_delay_seconds' => 1,
                'enable_batch_processing' => 1,
            ]
        );

        Settings::updateOrCreate(
            ['userId' => $demoUser->id],
            [
                'business_name' => 'Acme Global Commerce',
                'business_type' => 'Retail & E-commerce',
                'business_details' => 'Acme Global supplies lifestyle and tech accessories to retail customers worldwide.',
                'business_hours' => 'Mon-Fri 10:00 AM - 06:00 PM',
                'auto_ai_enabled' => true,
                'auto_ai_tone' => 'professional',
                'first_time_message' => 'Thank you for contacting Acme Global! An agent or AI assistant will be with you shortly.',
                'is_app_live' => 1,
                'wallet_min_balance' => 100.00,
                'wallet_price_marketing' => 0.85,
                'wallet_price_utility' => 0.35,
                'wallet_price_authentication' => 0.35,
                'batch_size' => 50,
                'batch_delay_seconds' => 1,
                'enable_batch_processing' => 1,
            ]
        );

        // 5. Seed Default Navigation Modules
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

        foreach ($defaultModules as $mod) {
            Module::updateOrCreate(['slug' => $mod['slug']], $mod);
        }
    }
}
