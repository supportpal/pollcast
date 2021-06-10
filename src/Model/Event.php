<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'pollcast_events';

    public $incrementing = false;

    /** @var string[] */
    protected $casts = [
        'channels' => 'json',
        'events'   => 'string',
        'payload'  => 'json',
    ];

    public function delay(string $time): int
    {
        /** @var Carbon $requested */
        $requested = Carbon::createFromFormat('Y-m-d H:i:s', $time);

        return $requested->diffInSeconds($this->createdAt());
    }

    public function createdAt(): Carbon
    {
        return $this->{$this->getCreatedAtColumn()};
    }
}
