<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::Group(['namespace' => 'Api\V1', 'prefix' => 'v1',], function () {

    Route::Group(['namespace' => 'DeviceClient', 'prefix' => 'client'], function () {
        //设备桩端相关
        Route::post('device/login', ['uses' => 'DeviceController@login']);
        Route::post('device/report', ['uses' => 'DeviceController@report',]);
        Route::post('device/setting', ['uses' => 'DeviceController@heartSet',]);
    });
});
