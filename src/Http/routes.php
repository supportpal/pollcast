<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'SupportPal\Pollcast\Http\Controller'], function () {
    Route::post('pollcast/connect', [
        'as'   => 'supportpal.pollcast.connect',
        'uses' => 'PollcastController@connect',
    ]);

    Route::post('pollcast/receive', [
        'as'   => 'supportpal.pollcast.receive',
        'uses' => 'PollcastController@receive',
    ]);
});
