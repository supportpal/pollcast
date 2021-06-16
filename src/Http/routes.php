<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'SupportPal\Pollcast\Http\Controller'], function () {
    Route::post('pollcast/receive', [
        'as'   => 'supportpal.pollcast.receive',
        'uses' => 'PollcastController@receive',
    ]);
});
