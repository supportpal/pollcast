<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

use function hash;
use function is_string;
use function serialize;

class Event extends Model
{
    /** @var string */
    protected $table = 'pollcast_events';

    /** @var bool */
    public $incrementing = false;

    /** @var string[] */
    protected $fillable = ['channel', 'event', 'payload'];

    /** @var string[] */
    protected $casts = [
        'channel' => 'string',
        'event'   => 'string',
        'payload' => 'json',
    ];

    public function touchTimestamps(): self
    {
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function setChannelAttribute($value): void
    {
        $this->attributes['channel'] = $this->normalizeChannelAttribute($value);
    }

    public function createdAt(): Carbon
    {
        return $this->{$this->getCreatedAtColumn()};
    }

    /**
     * @param mixed $value
     */
    protected function normalizeChannelAttribute($value): string
    {
        if ($value instanceof Channel) {
            return $value->name;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Only \Illuminate\Broadcasting\Channel or string values are permitted.');
        }

        return $value;
    }
}
