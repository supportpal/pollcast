<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $channel_id
 * @property-read string $socket_id
 * @property-read mixed[] $data
 */
class Member extends Model
{
    /** @var string */
    protected $table = 'pollcast_channel_members';

    /** @var string[] */
    protected $fillable = ['channel_id', 'socket_id', 'data'];

    /** @var string[] */
    protected $casts = [
        'channel_id' => 'int',
        'socket_id'  => 'string',
        'data'       => 'json',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
