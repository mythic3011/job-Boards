<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $userType = fake()->randomElement(['company', 'individual']);
        $loginId = fake()->unique()->userName();

        return [
            'idcode' => 'user_' . Str::uuid()->toString(),
            'login_id' => $loginId,
            'nickname' => fake()->name(),
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
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'individual',
        ]);
    }

}
