<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SupportPal\Pollcast\Model\Channel;

use function fake;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Channel>
     */
    protected $model = Channel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
