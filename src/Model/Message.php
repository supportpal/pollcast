<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $channel_id
 * @property-read int $member_id
 * @property-read string $event
 * @property-read mixed[] $payload
 */
class Message extends Model
{
    /** @var string */
    protected $table = 'pollcast_message_queue';

    /** @var string[] */
    protected $fillable = ['channel_id', 'member_id', 'event', 'payload'];

    /** @var string[] */
    protected $casts = [
        'channel_id' => 'int',
        'member_id'  => 'int',
        'event'      => 'string',
        'payload'    => 'json',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function touchTimestamps(): self
    {
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        return $this;
    }

    public function createdAt(): Carbon
    {
        return $this->{$this->getCreatedAtColumn()};
    }
}
