<?php declare(strict_types=1);

use Faker\Generator;
use SupportPal\Pollcast\Model\Channel;

if (! isset($factory)) {
    throw new RuntimeException('Variable $factory is not defined.');
}

$factory->define(Channel::class, function (Generator $faker) {
    return [
        'name' => $faker->name,
    ];
});
