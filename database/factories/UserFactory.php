<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    private static array $companySuffixes = [
        'Inc.', 'LLC', 'Corp.', 'Group', 'Solutions', 'Technologies', 'Services',
        'Partners', 'Consulting', 'Ventures', 'Industries', 'Systems', 'Labs',
    ];

    public function definition(): array
    {
        $userType = fake()->randomElement(['company', 'individual']);
        $loginId = fake()->unique()->userName();

        return [
            'idcode' => 'user_' . Str::uuid()->toString(),
            'login_id' => $loginId,
            'nickname' => $userType === 'company' ? $this->companyName() : fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make($this->generateValidPassword()),
            'user_type' => $userType,
            'profile_image_path' => null,
            'locked_until' => null,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'company',
            'nickname' => $this->companyName(),
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'individual',
            'nickname' => fake()->name(),
        ]);
    }

    private function companyName(): string
    {
        $suffix = fake()->randomElement(self::$companySuffixes);
        $style = fake()->numberBetween(1, 3);

        return match ($style) {
            1 => fake()->lastName() . ' & ' . fake()->lastName() . ' ' . $suffix,
            2 => fake()->city() . ' ' . $suffix,
            default => fake()->lastName() . ' ' . $suffix,
        };
    }

    /**
     * Generate a password that meets app requirements:
     * - At least 12 characters
     * - Uppercase, lowercase, number, special char
     * - Not in breach database (can't guarantee, but use strong random)
     */
    private function generateValidPassword(): string
    {
        $upper = fake()->randomElement(range('A', 'Z'));
        $lower = fake()->randomElement(range('a', 'z'));
        $digit = fake()->randomElement(range('0', '9'));
        $special = fake()->randomElement(['@', '$', '!', '%', '*', '?', '&']);

        $remaining = Str::random(fake()->numberBetween(8, 16));

        $chars = str_split($upper . $lower . $digit . $special . $remaining);
        shuffle($chars);

        return implode('', $chars);
    }
}
