<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Database\Factories\ChannelFactory;
use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $name
 */
class Channel extends Model
{
    use Uuid;
    use HasFactory;

    /** @var string */
    protected $table = 'pollcast_channel';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var string[] */
    protected $guarded = [];

    /** @var array<int, string> */
    protected $fillable = ['name'];

    /** @var array<string, string> */
    protected $casts = [
        'name' => 'string',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory<Channel>
     */
    protected static function newFactory()
    {
        return ChannelFactory::new();
    }
}
