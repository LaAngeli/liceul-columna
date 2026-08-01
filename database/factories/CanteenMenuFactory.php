<?php

namespace Database\Factories;

use App\Models\CanteenMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanteenMenu>
 */
class CanteenMenuFactory extends Factory
{
    protected $model = CanteenMenu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_date' => fake()->unique()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'breakfast_main' => 'Terci din crupe de gris cu lapte',
            'breakfast_fruit' => 'Mere proaspete',
            'breakfast_bakery' => 'Chiflă cu mac',
            'breakfast_drink' => 'Amestec de plante și fructe',
            'lunch_first' => 'Supă din linte roșie',
            'lunch_second' => 'Pârjoală din carne de vițel',
            'lunch_side' => 'Paste făinoase cu unt',
            'lunch_salad' => 'Legume proaspete porționate',
            'lunch_drink' => 'Compot din fructe uscate',
            'lunch_fruit' => 'Mere',
            'notes' => null,
        ];
    }
}
