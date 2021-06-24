<?php declare(strict_types=1);

use Faker\Generator;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

if (! isset($factory)) {
    throw new RuntimeException('Variable $factory is not defined.');
}

$factory->define(Message::class, function (Generator $faker) {
    $member = factory(Member::class)->create();

    return [
        'channel_id' => function () {
            return factory(Channel::class)->create()->id;
        },
        'member_id'  => null,
        'event'      => 'Illuminate\Notifications\Events\BroadcastNotificationCreated',
        'payload'    => [
            'title'  => 'Operator Logged In',
            'text'   => 'John Doe has logged in to the operator panel.',
            'id'     => $faker->uuid,
            'type'   => 'App\\Modules\\User\\Notifications\\OperatorLogin',
            'socket' => $member->socket_id,
        ],
    ];
});
