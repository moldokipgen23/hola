<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        $settings = [
            // General
            ['key' => 'site_name', 'value' => 'Hola - Lamka Directory', 'group' => 'general'],
            ['key' => 'site_tagline', 'value' => 'Your Local Guide for Lamka / Churachandpur', 'group' => 'general'],
            ['key' => 'site_description', 'value' => 'Discover businesses, services, and products in Lamka. Find restaurants, hotels, shops, and more.', 'group' => 'general'],
            ['key' => 'site_logo', 'value' => null, 'group' => 'general'],
            ['key' => 'site_favicon', 'value' => null, 'group' => 'general'],
            ['key' => 'site_email', 'value' => 'hello@hola.app', 'group' => 'general'],
            ['key' => 'site_phone', 'value' => '+91 9876543210', 'group' => 'general'],
            ['key' => 'site_address', 'value' => 'Lamka, Churachandpur, Manipur, India', 'group' => 'general'],
            ['key' => 'site_currency', 'value' => '₹', 'group' => 'general'],

            // SEO
            ['key' => 'seo_title', 'value' => 'Hola - Lamka Directory | Find Local Businesses in Churachandpur', 'group' => 'seo'],
            ['key' => 'seo_description', 'value' => 'Discover the best businesses in Lamka, Churachandpur. Search restaurants, hotels, shops, services and more. Your complete local business directory.', 'group' => 'seo'],
            ['key' => 'seo_keywords', 'value' => 'Lamka directory, Churachandpur businesses, local guide, restaurants Lamka, hotels Churachandpur, shops Lamka, Manipur business directory', 'group' => 'seo'],
            ['key' => 'seo_og_image', 'value' => null, 'group' => 'seo'],
            ['key' => 'seo_twitter_handle', 'value' => '@holalamka', 'group' => 'seo'],
            ['key' => 'seo_google_analytics', 'value' => null, 'group' => 'seo'],
            ['key' => 'seo_robots', 'value' => 'User-agent: *\nAllow: /', 'group' => 'seo'],

            // Social
            ['key' => 'social_facebook', 'value' => null, 'group' => 'social'],
            ['key' => 'social_instagram', 'value' => null, 'group' => 'social'],
            ['key' => 'social_twitter', 'value' => null, 'group' => 'social'],
            ['key' => 'social_youtube', 'value' => null, 'group' => 'social'],
            ['key' => 'social_whatsapp', 'value' => null, 'group' => 'social'],
            ['key' => 'social_linkedin', 'value' => null, 'group' => 'social'],

            // Footer
            ['key' => 'footer_text', 'value' => '© 2026 Hola. All rights reserved.', 'group' => 'footer'],
            ['key' => 'footer_about', 'value' => 'Hola is the most trusted local business directory for Lamka / Churachandpur district.', 'group' => 'footer'],
            ['key' => 'footer_links', 'value' => json_encode([
                ['label' => 'About Us', 'url' => '/about'],
                ['label' => 'Contact', 'url' => '/contact'],
                ['label' => 'Privacy Policy', 'url' => '/privacy'],
                ['label' => 'Terms of Service', 'url' => '/terms'],
            ]), 'group' => 'footer'],

            // Contact Page
            ['key' => 'contact_heading', 'value' => 'Get in Touch', 'group' => 'contact'],
            ['key' => 'contact_email', 'value' => 'hello@hola.app', 'group' => 'contact'],
            ['key' => 'contact_phone', 'value' => '+91 9876543210', 'group' => 'contact'],
            ['key' => 'contact_address', 'value' => 'Lamka, Churachandpur, Manipur 795128, India', 'group' => 'contact'],
            ['key' => 'contact_map_lat', 'value' => '24.3667', 'group' => 'contact'],
            ['key' => 'contact_map_lng', 'value' => '93.7000', 'group' => 'contact'],
        ];

        foreach ($settings as $setting) {
            \DB::table('settings')->insert([
                ...$setting,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
