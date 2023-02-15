<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;

use function fake;

class MemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'channel_id' => Channel::factory(),
            'socket_id'  => fake()->uuid,
            'data'       => null,
        ];
    }
}
