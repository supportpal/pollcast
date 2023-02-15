<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

use function fake;

class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $member = Member::factory()->create();

        return [
            'channel_id' => Channel::factory(),
            'member_id'  => null,
            'event'      => 'Illuminate\Notifications\Events\BroadcastNotificationCreated',
            'payload'    => [
                'title'  => 'Operator Logged In',
                'text'   => 'John Doe has logged in to the operator panel.',
                'id'     => fake()->uuid,
                'type'   => 'App\\Modules\\User\\Notifications\\OperatorLogin',
                'socket' => $member->socket_id,
            ],
        ];
    }
}
