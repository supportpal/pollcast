<?php declare(strict_types=1);

use Faker\Generator;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;

if (! isset($factory)) {
    throw new RuntimeException('Variable $factory is not defined.');
}

$factory->define(Member::class, function (Generator $faker) {
    return [
        'channel_id' => function () {
            return factory(Channel::class)->create()->id;
        },
        'socket_id'  => $faker->uuid,
        'data'       => null,
    ];
});
