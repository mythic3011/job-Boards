<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesInMemorySqlite
{
    protected function useInMemorySqlite(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    }

    protected function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idcode')->unique();
            $table->string('login_id')->unique();
            $table->string('nickname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('user_type');
            $table->string('registration_state')->default('active');
            $table->text('profile_image_path')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function createAuditLogsTable(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('occurred_at');
            $table->timestamp('admitted_at')->nullable();
            $table->uuid('request_id');
            $table->string('source')->nullable();
            $table->string('outcome')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->string('actor_type');
            $table->string('event_type');
            $table->string('method');
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_idcode')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['source', 'request_id', 'event_type', 'outcome', 'target_idcode'],
                'audit_logs_canonical_identity_unique',
            );
        });
    }

    protected function createSettingsTable(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    protected function createJobPostingsTable(): void
    {
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idcode')->unique();
            $table->uuid('company_user_id');
            $table->string('title');
            $table->text('requirement');
            $table->text('duty');
            $table->unsignedInteger('salary_from')->nullable();
            $table->unsignedInteger('salary_to')->nullable();
            $table->timestamps();
        });
    }

    protected function createApplicationsTable(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idcode')->unique();
            $table->uuid('job_id');
            $table->uuid('applicant_user_id');
            $table->text('message')->nullable();
            $table->text('decision_message')->nullable();
            $table->timestamp('decision_message_read_at')->nullable();
            $table->string('cv_file_path');
            $table->string('cv_original_name');
            $table->string('cv_mime');
            $table->unsignedBigInteger('cv_size_bytes');
            $table->string('cv_sha256');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
    }

    protected function createPermissionTables(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }
}
