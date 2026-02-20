<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idcode' => 'app_' . Str::uuid()->toString(),
            'job_id' => JobPosting::factory(),
            'applicant_user_id' => User::factory()->individual(),
            'message' => fake()->optional()->paragraph(),
            'cv_file_path' => 'cvs/' . Str::uuid()->toString() . '.pdf',
            'cv_original_name' => 'resume.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => fake()->numberBetween(50000, 5000000),
            'cv_sha256' => Str::random(64),
        ];
    }
}
