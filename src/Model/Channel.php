<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $name
 */
class Channel extends Model
{
    use Uuid;

    /** @var string */
    protected $table = 'pollcast_channel';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var string[] */
    protected $guarded = [];

    /** @var string[] */
    protected $fillable = ['name'];

    /** @var string[] */
    protected $casts = [
        'name' => 'string',
    ];
}
