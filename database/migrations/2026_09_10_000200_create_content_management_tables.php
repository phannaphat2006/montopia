<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('tagline', 255)->nullable();
            $table->text('description');
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->text('address');
            $table->string('registration_number', 30)->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('slug', 160)->unique();
            $table->string('short_description', 255);
            $table->text('description');
            $table->string('icon_label', 20)->nullable();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('price_label', 100);
            $table->string('delivery_time', 100)->nullable();
            $table->text('description');
            $table->text('features')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 190)->unique();
            $table->string('excerpt', 300);
            $table->longText('content');
            $table->string('image_url', 255)->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('services');
        Schema::dropIfExists('company_profiles');
    }
};
