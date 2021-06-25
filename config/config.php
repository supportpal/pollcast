<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Channel Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish a channel to
    | remain idle before it is garbage collected.
    |
    */
    'channel_lifetime' => 1440,

    /*
    |--------------------------------------------------------------------------
    | Member Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of seconds that you wish a member to
    | remain connected to a channel before it is garbage collected.
    |
    */
    'member_lifetime' => 10,

    /*
    |--------------------------------------------------------------------------
    | Message Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of seconds that you wish a message to
    | remain in the queue before it is garbage collected.
    |
    */
    'message_lifetime' => 10,

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection Lottery
    |--------------------------------------------------------------------------
    |
    | Here are the chances that it will run garbage collection on a given
    | broadcasting of events. By default, the odds are 1 out of 10.
    |
    */
    'lottery' => [1, 10],
];
