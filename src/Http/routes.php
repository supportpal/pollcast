<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SupportPal\Pollcast\Http\Middleware\AddSocketId;
use SupportPal\Pollcast\Http\Middleware\VerifySocketId;

Route::group([
    'prefix' => 'pollcast',
    'namespace' => 'SupportPal\Pollcast\Http\Controller',
    'middleware' => [AddSocketId::class],
], function () {
    Route::post('connect', [
        'as'   => 'supportpal.pollcast.connect',
        'uses' => 'ChannelController@connect',
    ]);

    Route::group(['middleware' => [VerifySocketId::class]], function () {
        Route::post('channel/subscribe', [
            'as' => 'supportpal.pollcast.subscribe',
            'uses' => 'ChannelController@subscribe',
        ])->middleware('web');

        Route::post('channel/unsubscribe', [
            'as' => 'supportpal.pollcast.unsubscribe',
            'uses' => 'ChannelController@unsubscribe',
        ]);

        Route::post('subscribe/messages', [
            'as' => 'supportpal.pollcast.receive',
            'uses' => 'SubscriptionController@messages',
        ]);

        Route::post('publish', [
            'as' => 'supportpal.pollcast.publish',
            'uses' => 'PublishController@publish',
        ]);
    });
});
