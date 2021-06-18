<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Illuminate\Broadcasting\Channel as PublicChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function is_string;

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

    /**
     * @param mixed $value
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $this->normalizeNameAttribute($value);
    }

    /**
     * @param mixed $value
     */
    protected function normalizeNameAttribute($value): string
    {
        if ($value instanceof PresenceChannel) {
            return 'presence-' . $value->name;
        }

        if ($value instanceof PrivateChannel) {
            return 'private-' . $value->name;
        }

        if ($value instanceof PublicChannel) {
            return $value->name;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Only \Illuminate\Broadcasting\Channel or string values are permitted.');
        }

        return $value;
    }
}
