<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $channel_id
 * @property-read string $member_id
 * @property-read string $event
 * @property-read mixed[] $payload
 */
class Message extends Model
{
    use HasFactory, HasUlids;

    /** @var string */
    protected $table = 'pollcast_message_queue';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var string[] */
    protected $guarded = [];

    /** @var array<int, string> */
    protected $fillable = ['channel_id', 'member_id', 'event', 'payload'];

    /** @var array<string, string> */
    protected $casts = [
        'channel_id' => 'string',
        'member_id'  => 'string',
        'event'      => 'string',
        'payload'    => 'json',
    ];

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @return BelongsTo<Channel, Message>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<Member, Message>
     */
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

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory<Message>
     */
    protected static function newFactory()
    {
        return MessageFactory::new();
    }
}
