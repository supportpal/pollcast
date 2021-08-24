<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

use function json_encode;

/**
 * @property-read string $id
 * @property-read string $channel_id
 * @property-read string $member_id
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

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * @param mixed[] $payload
     * @return mixed[]
     */
    public static function make(string $channel, string $event, array $payload, ?string $member = null): array
    {
        $instance = new self;

        return [
            $instance->getKeyName() => $instance->generateUuid(),
            'channel_id'            => $channel,
            'member_id'             => $member,
            'event'                 => $event,
            'payload'               => json_encode($payload),
            'created_at'            => Date::now(),
            'updated_at'            => Date::now(),
        ];
    }
}
