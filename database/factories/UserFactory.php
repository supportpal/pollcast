<?php declare(strict_types=1);

use Faker\Generator;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;

if (! isset($factory)) {
    throw new RuntimeException('Variable $factory is not defined.');
}

$factory->define(User::class, function (Generator $faker) {
    return [
        'name'           => $faker->name,
        'email'          => $faker->unique()->email,
        'password'       => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'remember_token' => Str::random(10),
    ];
});
