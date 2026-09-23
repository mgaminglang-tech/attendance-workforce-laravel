<?php

namespace Database\Factories;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeIn = CarbonImmutable::instance(fake()->dateTimeBetween('-3 months', 'now'))
            ->setTimezone(config('app.timezone'));

        return [
            'employee_id' => Employee::factory(),
            'work_date' => $timeIn->toDateString(),
            'time_in_at' => $timeIn,
            'time_out_at' => $timeIn->addHours(8),
            'work_arrangement' => fake()->randomElement(WorkArrangement::cases()),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'time_out_at' => null,
        ]);
    }

    public function legacy(): static
    {
        return $this->state(fn (array $attributes): array => [
            'work_arrangement' => null,
        ]);
    }
}
