<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class JobPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idcode' => 'job_' . Str::uuid()->toString(),
            'company_user_id' => User::factory()->company(),
            'title' => fake()->jobTitle(),
            'requirement' => fake()->paragraphs(3, true),
            'duty' => fake()->paragraphs(2, true),
            'salary' => fake()->optional()->numerify('$##,### - $##,###'),
        ];
    }
}
