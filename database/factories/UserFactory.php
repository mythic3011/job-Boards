<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

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
            'password' => static::$password ??= Hash::make('password'),
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
}
