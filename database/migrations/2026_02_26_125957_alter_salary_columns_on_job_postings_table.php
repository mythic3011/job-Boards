<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->unsignedInteger('salary_from')->nullable()->after('duty');
            $table->unsignedInteger('salary_to')->nullable()->after('salary_from');
        });

        // Migrate existing string salary data to integer columns
        DB::statement("
            UPDATE job_postings
            SET
                salary_from = NULLIF(regexp_replace(split_part(salary, ' - ', 1), '[^0-9]', '', 'g'), '')::integer,
                salary_to   = CASE
                                WHEN salary LIKE '%-%'
                                THEN NULLIF(regexp_replace(split_part(salary, ' - ', 2), '[^0-9]', '', 'g'), '')::integer
                                ELSE NULL
                              END
            WHERE salary IS NOT NULL
        ");

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('salary');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('salary')->nullable()->after('duty');
        });

        DB::statement("
            UPDATE job_postings
            SET salary = CASE
                WHEN salary_from IS NOT NULL AND salary_to IS NOT NULL
                    THEN '$' || to_char(salary_from, 'FM999,999,999') || ' - $' || to_char(salary_to, 'FM999,999,999')
                WHEN salary_from IS NOT NULL
                    THEN '$' || to_char(salary_from, 'FM999,999,999')
                ELSE NULL
            END
        ");

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['salary_from', 'salary_to']);
        });
    }
};
