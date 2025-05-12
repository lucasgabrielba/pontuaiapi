<?php

namespace Database\Factories;

use Domains\Shared\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition()
    {
        return [
            'id' => str()->ulid(),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'complement' => fake()->secondaryAddress(),
            'district' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => substr(fake()->state(), 0, 2),
            'country' => fake()->country(),
            'postal_code' => fake()->postcode(),
            'reference' => fake()->sentence(),
        ];
    }
}
