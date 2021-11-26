<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'office_id' => OfficeFactory::Factory(),
            'price ' => $this->faker->numberBetween(10_000, 20_000),
            'status' => Reservation::STATUS_ACTIVE,
            'start_date' =>  now()->addDay(1)->formate('Y-m-d'),
            'end_date' =>  now()->addDay(5)->formate('Y-m-d'),

        ];
    }
}
