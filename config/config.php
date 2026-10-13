<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | Here you may specify how often the polling for new messages occurs in
    | milliseconds, we recommend a low value to better replicate how a
    | web sockets environment would work.
    |
    */
    'polling_interval' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection Lottery
    |--------------------------------------------------------------------------
    |
    | Here are the chances that garbage collection runs after a given pollcast
    | request has been sent to the client. By default, the odds are 1 out of 10.
    |
    */
    'gc_lottery' => [1, 10],
];
