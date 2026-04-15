<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->timestampTz('admitted_at')->nullable()->after('occurred_at');
            $table->string('source', 32)->nullable()->after('request_id');
            $table->string('outcome', 32)->nullable()->after('source');

            $table->index('admitted_at');
            $table->index('source');
            $table->index('outcome');
            $table->unique(
                ['source', 'request_id', 'event_type', 'outcome', 'target_idcode'],
                'audit_logs_canonical_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique('audit_logs_canonical_identity_unique');
            $table->dropIndex(['admitted_at']);
            $table->dropIndex(['source']);
            $table->dropIndex(['outcome']);
            $table->dropColumn(['admitted_at', 'source', 'outcome']);
        });
    }
};
