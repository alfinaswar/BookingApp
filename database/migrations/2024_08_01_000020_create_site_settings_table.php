<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas & branding sistem (pemilik aplikasi), terpisah dari `settings`
 * yang menyimpan parameter operasional (DP, pajak, TTL hold).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();

            // Identitas
            $table->string('app_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('business_type')->nullable();
            $table->text('description')->nullable();

            // Legal
            $table->string('business_license', 100)->nullable();
            $table->string('legal_name')->nullable();

            // Kontak
            $table->string('email', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 100)->nullable()->default('Indonesia');

            // Sosial media
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('youtube')->nullable();

            // File branding (path relatif di disk public)
            $table->string('logo')->nullable();
            $table->string('logo_light')->nullable();
            $table->string('favicon')->nullable();
            $table->string('login_image')->nullable();

            // Warna
            $table->string('color_primary', 7)->default('#1F573A');
            $table->string('color_secondary', 7)->default('#2FB344');
            $table->string('color_accent', 7)->default('#F59F00');

            // Preferensi tampilan
            $table->boolean('show_company_name')->default(true);
            $table->string('footer_text')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};