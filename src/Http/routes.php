<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'pollcast',
    'middleware' => ['web'],
    'namespace' => 'SupportPal\Pollcast\Http\Controller'
], function () {
    Route::post('connect', [
        'as'   => 'supportpal.pollcast.connect',
        'uses' => 'ChannelController@connect',
    ]);

    Route::post('channel/subscribe', [
        'as'   => 'supportpal.pollcast.subscribe',
        'uses' => 'ChannelController@subscribe',
    ]);

    Route::post('channel/unsubscribe', [
        'as'   => 'supportpal.pollcast.unsubscribe',
        'uses' => 'ChannelController@unsubscribe',
    ]);

    Route::post('subscribe/messages', [
        'as'   => 'supportpal.pollcast.receive',
        'uses' => 'SubscriptionController@messages',
    ]);

    Route::post('publish', [
        'as'   => 'supportpal.pollcast.publish',
        'uses' => 'PublishController@publish',
    ]);
});
