<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Carbon\Carbon;
use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
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
    use Uuid;

    /** @var string */
    protected $table = 'pollcast_message_queue';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var string[] */
    protected $guarded = [];

    /** @var string[] */
    protected $fillable = ['channel_id', 'member_id', 'event', 'payload'];

    /** @var string[] */
    protected $casts = [
        'channel_id' => 'string',
        'member_id'  => 'string',
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

    public function setUuid(): self
    {
        $this->{$this->getKeyName()} = $this->generateUuid();

        return $this;
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
