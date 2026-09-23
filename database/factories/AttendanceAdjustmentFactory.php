<?php

namespace Database\Factories;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceAdjustment>
 */
class AttendanceAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $previousTimeIn = CarbonImmutable::instance(fake()->dateTimeBetween('-3 months', '-1 day'))
            ->setTimezone(config('app.timezone'));
        $correctedTimeIn = $previousTimeIn->addMinutes(15);

        return [
            'attendance_session_id' => AttendanceSession::factory(),
            'administrator_id' => User::factory()->admin(),
            'reason' => fake()->sentence(),
            'previous_work_date' => $previousTimeIn->toDateString(),
            'previous_time_in_at' => $previousTimeIn,
            'previous_time_out_at' => $previousTimeIn->addHours(8),
            'corrected_work_date' => $correctedTimeIn->toDateString(),
            'corrected_time_in_at' => $correctedTimeIn,
            'corrected_time_out_at' => $correctedTimeIn->addHours(8),
            'corrected_at' => now(config('app.timezone')),
        ];
    }
}
