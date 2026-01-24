<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('idcode')->unique();
            $table->uuid('company_user_id')->index();
            $table->string('title');
            $table->text('requirement');
            $table->text('duty');
            $table->string('salary')->nullable();
            $table->timestampsTz();
            // FK: company user id
            $table->foreign('company_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('idcode')->unique();
            $table->uuid('job_id')->index();
            $table->uuid('applicant_user_id')->index();
            $table->text('message')->nullable();
            $table->text('cv_file_path');
            $table->string('cv_original_name')->nullable();
            $table->string('cv_mime')->nullable();
            $table->bigInteger('cv_size_bytes')->nullable();
            $table->char('cv_sha256', 64)->nullable();
            $table->timestampsTz();
            
            // FK: job id from job_postings table
            $table->foreign('job_id')
                ->references('id')
                ->on('job_postings')
                ->onDelete('cascade');
            
            // FK: applicant user id from users table
            $table->foreign('applicant_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            // unique: job id and applicant user id
            $table->unique(['job_id', 'applicant_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
        Schema::dropIfExists('job_postings');
    }
};
