<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $name
 */
class Channel extends Model
{
    /** @var string */
    protected $table = 'pollcast_channel';

    /** @var string[] */
    protected $fillable = ['name'];

    /** @var string[] */
    protected $casts = [
        'name' => 'string',
    ];
}
