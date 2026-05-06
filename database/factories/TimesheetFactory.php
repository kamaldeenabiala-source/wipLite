<?php

// database/factories/TimesheetFactory.php
namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimesheetFactory extends Factory
{
    public function definition(): array
    {
<<<<<<< HEAD
        $start = $this->faker->dateTimeBetween('-1 month', 'now');
        $start->modify('monday this week');

        $end = (clone $start);
        $end->modify('sunday this week');

        return [
            'employee_id' => Employee::factory(),

            'period_start' => $start->format('Y-m-d'),
            'period_end'   => $end->format('Y-m-d'),

            'status' => $this->faker->randomElement([
                'brouillon',
                'soumis',
                'valide'
            ]),

            'validated_by' => null,
            'validated_at' => null,
=======
        $start = now()->startOfWeek();
        return [
            'employee_id' => Employee::inRandomOrder()->first() ?? Employee::factory(),
            'period_start' => $start,
            'period_end' => (clone $start)->endOfWeek(),
            'status' => $this->faker->randomElement(['brouillon', 'soumis', 'valide']),
            'validated_by' => Employee::whereHas('position', fn($q) => $q->where('code', 'CP'))->inRandomOrder()->first()?->id,
            'validated_at' => now(),
>>>>>>> 2b06f4214a1276acce479a4c988985fbfc7914fc
        ];
    }
}
