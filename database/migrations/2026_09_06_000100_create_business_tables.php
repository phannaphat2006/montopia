<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('category', 50)->index();
            $table->text('description');
            $table->string('image_url', 255);
            $table->string('technologies', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 100);
            $table->string('client_email', 150)->index();
            $table->string('client_phone', 20);
            $table->string('budget_range', 50);
            $table->text('project_scope');
            $table->enum('status', ['pending', 'contacted', 'accepted', 'rejected'])->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('inquiry_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('reply_message');
            $table->timestamp('sent_at')->useCurrent();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_name', 150);
            $table->string('client_name', 100);
            $table->decimal('total_budget', 10, 2);
            $table->enum('status', ['active', 'completed', 'archived'])->default('active')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->enum('status', ['pending', 'in_progress', 'delivered', 'approved'])->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('inquiry_replies');
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('portfolios');
    }
};
