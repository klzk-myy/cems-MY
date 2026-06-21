<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserNotificationPreferenceFactory extends Factory
{
    protected $model = UserNotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_type' => $this->faker->randomElement(NotificationType::cases()),
            'email_enabled' => $this->faker->boolean(80),
            'sms_enabled' => $this->faker->boolean(30),
            'in_app_enabled' => $this->faker->boolean(90),
            'push_enabled' => $this->faker->boolean(40),
            'webhook_url' => $this->faker->optional()->url(),
            'custom_settings' => $this->faker->optional()->randomElement([
                ['ttl' => 3600],
                ['priority' => 'high'],
                ['quiet_hours' => '22:00-08:00'],
                null,
            ]),
        ];
    }
}
