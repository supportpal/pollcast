<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $channel_id
 * @property-read string $socket_id
 * @property-read mixed[] $data
 */
class Member extends Model
{
    use Uuid;

    /** @var string */
    protected $table = 'pollcast_channel_members';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var string[] */
    protected $guarded = [];

    /** @var string[] */
    protected $fillable = ['channel_id', 'socket_id', 'data'];

    /** @var string[] */
    protected $casts = [
        'channel_id' => 'string',
        'socket_id'  => 'string',
        'data'       => 'json',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
