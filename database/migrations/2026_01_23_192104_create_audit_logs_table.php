<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->timestampTz('occurred_at')->useCurrent();
            $table->uuid('request_id')->index();

            $table->uuid('actor_user_id')->nullable()->index();
            $table->string('actor_type', 16); // actor_type: guest|user|system

            $table->string('event_type', 64)->index();

            $table->string('method', 8);
            $table->text('path');
            $table->unsignedSmallInteger('status_code')->index();

            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->string('target_type', 32)->nullable()->index();
            $table->string('target_idcode', 80)->nullable()->index();

            $table->json('meta')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
