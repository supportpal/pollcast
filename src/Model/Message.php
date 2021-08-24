<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use DateTimeImmutable;
use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

use function sprintf;

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

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.u';

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

    /**
     * To get around PHP 7.2 PDO bug with fractional datetimes - https://bugs.php.net/bug.php?id=76386
     * https://github.com/laravel/framework/issues/3506#issuecomment-383877242
     */
    protected function asDateTime($value): Carbon
    {
        try {
            return parent::asDateTime($value);
        } catch (InvalidArgumentException $e) {
            return parent::asDateTime(new DateTimeImmutable($value));
        }
    }

    /**
     * To get around PHP 7.2 PDO bug with fractional datetimes - https://bugs.php.net/bug.php?id=76386
     * https://github.com/laravel/framework/issues/3506#issuecomment-383877242
     */
    public function newQuery(): Builder
    {
        $query = parent::newQuery();

        if ($this->usesTimestamps()) {
            $table = $this->getTable();
            $createdAt = $this->getCreatedAtColumn();
            $updatedAt = $this->getUpdatedAtColumn();

            $query->select()
                ->addSelect(DB::raw(sprintf('CAST(%s.%s AS CHAR) as %s', $table, $createdAt, $createdAt)))
                ->addSelect(DB::raw(sprintf('CAST(%s.%s AS CHAR) as %s', $table, $updatedAt, $updatedAt)));
        }

        return $query;
    }
}
