<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuditLogFactory extends Factory
{
    private static array $eventTypes = [
        'user_login', 'login_failed', 'user_registered',
        'account_locked', 'account_locked_attempt',
        'profile_updated', 'profile_image_deleted', 'password_updated',
        '2fa.enabled', '2fa.confirmed', '2fa.disabled', '2fa.recovery_codes_regenerated',
        'cv_download', 'admin.job.updated',
        'password_reset.link_sent', 'password_reset_completed',
        'suspicious_user_agent', 'admin_probe',
    ];

    private static array $actorTypes = ['user', 'guest'];

    public function definition(): array
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $statusCodes = [200, 201, 204, 400, 401, 403, 404, 422, 500];

        return [
            'id' => Str::uuid()->toString(),
            'occurred_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'request_id' => Str::uuid()->toString(),
            'actor_user_id' => User::factory(),
            'actor_type' => fake()->randomElement(self::$actorTypes),
            'event_type' => fake()->randomElement(self::$eventTypes),
            'method' => fake()->randomElement($methods),
            'path' => '/' . fake()->words(3, true),
            'status_code' => fake()->randomElement($statusCodes),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'target_type' => fake()->optional()->randomElement(['User', 'JobPosting', 'Application']),
            'target_idcode' => fake()->optional()->uuid(),
            'meta' => fake()->optional()->randomElement([
                null,
                ['action' => 'test', 'details' => fake()->sentence()],
            ]),
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => fake()->randomElement([200, 201, 204]),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => fake()->randomElement([400, 401, 403, 404, 422, 500]),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_user_id' => $user->getKey(),
            'actor_type' => 'user',
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => fake()->dateTimeBetween('-24 hours', 'now'),
        ]);
    }
}
