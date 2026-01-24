<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    public function definition(): array
    {
        $keys = [
            'app_name',
            'app_url',
            'setup_completed',
            'max_file_size',
            'allowed_file_types',
            'maintenance_mode',
            'registration_enabled',
            'email_verification_required',
        ];

        return [
            'key' => fake()->unique()->randomElement($keys),
            'value' => fake()->randomElement([
                'true',
                'false',
                fake()->url(),
                fake()->company(),
                fake()->numberBetween(1, 1000),
                'pdf,doc,docx',
            ]),
        ];
    }

    public function boolean(bool $value = true): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value ? 'true' : 'false',
        ]);
    }

    public function string(string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value,
        ]);
    }

    public function setupCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'setup_completed',
            'value' => 'true',
        ]);
    }
}
