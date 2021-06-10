<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Model;

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
}
