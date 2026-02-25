<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

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

        // generate a random, reasonably strong password for demo users
        // ensure it meets the same validation rules used by the app
        $passwordRules = (new \App\Actions\Fortify\PasswordValidationRules())->passwordRules();
        $rawPwd = $this->generateValidPassword($passwordRules);

        return [
            'idcode' => 'user_' . Str::uuid()->toString(),
            'login_id' => $loginId,
            'nickname' => $userType === 'company' ? $this->companyName() : fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make($rawPwd),
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
     * Generate a random password string that satisfies the given validation rules.
     *
     * @param array<int, mixed> $rules
     */
    private function generateValidPassword(array $rules): string
    {
        do {
            $pwd = fake()->password(12, 24);
            $validator = Validator::make(['password' => $pwd], ['password' => $rules]);
        } while ($validator->fails());

        return $pwd;
    }
}
