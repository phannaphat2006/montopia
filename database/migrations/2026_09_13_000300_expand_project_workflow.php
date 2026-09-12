<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });

        $this->expandProjectStatus();

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->foreignId('updated_by')->nullable()->after('end_date')->constrained('users')->nullOnDelete();
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->default('note')->index();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->boolean('visible_to_client')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_path', 500)->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->enum('visibility', ['client', 'internal'])->default('client')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
        Schema::dropIfExists('project_updates');

        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('progress_percent');
        });

        $this->contractProjectStatus();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'last_login_at']);
        });
    }

    private function expandProjectStatus(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY status ENUM('active','planned','in_progress','review','completed','archived') NOT NULL DEFAULT 'active'");
            DB::table('projects')->where('status', 'active')->update(['status' => 'in_progress']);
            DB::statement("ALTER TABLE projects MODIFY status ENUM('planned','in_progress','review','completed','archived') NOT NULL DEFAULT 'planned'");

            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->string('status', 30)->default('planned')->change();
        });
        DB::table('projects')->where('status', 'active')->update(['status' => 'in_progress']);
    }

    private function contractProjectStatus(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY status ENUM('active','planned','in_progress','review','completed','archived') NOT NULL DEFAULT 'active'");
            DB::table('projects')->whereIn('status', ['planned', 'in_progress', 'review'])->update(['status' => 'active']);
            DB::statement("ALTER TABLE projects MODIFY status ENUM('active','completed','archived') NOT NULL DEFAULT 'active'");

            return;
        }

        DB::table('projects')->whereIn('status', ['planned', 'in_progress', 'review'])->update(['status' => 'active']);
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->change();
        });
    }
};
