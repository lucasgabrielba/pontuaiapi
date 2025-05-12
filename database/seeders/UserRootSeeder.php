<?php

namespace Database\Seeders;

use Domains\Users\Enums\UserStatus;
use Domains\Users\Models\User;
use Illuminate\Database\Seeder;

class UserRootSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::create([
            'id' => '01JB84AKV7MZ9J5AB80RJEVDXT',
            'name' => 'Root User',
            'email' => 'root@root.com',
            'password' => bcrypt('root'),
            'status' => UserStatus::ACTIVE,
        ]);

        $user->assignRole('super_admin');
    }
}
