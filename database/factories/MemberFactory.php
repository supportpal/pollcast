<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;

use function fake;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Member>
     */
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
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
