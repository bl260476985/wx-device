<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::Group(['middleware' => ['throttle']], function () {
    Route::any('admin/debug/test', ['uses' => 'DebugController@test']);
    Route::any('admin/debug/test2', ['uses' => 'DebugController@test2']);
});

Route::group([], function () {
    Route::get('/', ['uses' => 'WelcomeController@index']);
    Route::match(['get', 'post'], '/notify', ['uses' => 'WelcomeController@notify']);
    Route::match(['get', 'post'], '/action', ['uses' => 'WelcomeController@action']);
    Route::get('/refreshaccesstoken', ['uses' => 'WelcomeController@refreshAccessToken']);
    Route::get('/minirefreshaccesstoken', ['uses' => 'WelcomeController@refreshMiniproAccessToken']);
    Route::get('/createmenu', ['uses' => 'WelcomeController@createMenu']);
    Route::match(['get', 'post'], '/load', ['uses' => 'WelcomeController@load']);
    Route::match(['get', 'post'], '/save', ['uses' => 'WelcomeController@save']);


    //   小程序使用路由
    Route::match(['get', 'post'], 'mini/action', ['uses' => 'WelcomeController@miniAction']);
});


Route::Group(['namespace' => 'Api\V1', 'prefix' => 'v1', 'middleware' => ['datacheck']], function () {
    Route::Group(['namespace' => 'DeviceClient', 'prefix' => 'client'], function () {
        //用户绑定
        Route::post('user/bind', ['uses' => 'DeviceController@login']);
    });
});

//小程序接口
Route::Group(['namespace' => 'Api\V2', 'prefix' => 'v2', 'middleware' => ['header']], function () {
    Route::Group(['namespace' => 'User', 'prefix' => 'user',], function () {
        //用户绑定
        Route::post('user/bind', ['uses' => 'UserController@bind']);
        Route::post('user/unbind', ['middleware' => ['wxlogger'], 'uses' => 'UserController@unbind']);
        Route::post('user/info', ['middleware' => ['wxlogger'], 'uses' => 'UserController@getCurrent']);
    });
    Route::Group(['namespace' => 'Device', 'prefix' => 'device'], function () {
        //用户绑定
        Route::match(['post', 'get'], 'device/search', ['middleware' => ['wxlogger'], 'uses' => 'DeviceController@search']);
        Route::match(['post', 'get'], 'device/get', ['middleware' => ['wxlogger'], 'uses' => 'DeviceController@get']);
        Route::match(['post', 'get'], 'device/renew', ['middleware' => ['wxlogger'], 'uses' => 'DeviceController@update']);
        Route::match(['post', 'get'], 'device/unlock', ['middleware' => ['wxlogger'], 'uses' => 'DeviceController@unlock']);
        Route::match(['post', 'get'], 'pic/upload', ['middleware' => ['wxlogger'], 'uses' => 'DeviceController@uploadPic']);
        Route::match(['post', 'get'], 'device/log', ['middleware' => ['wxlogger'], 'uses' => 'DeviceController@searchLog']);
        Route::match(['post', 'get'], 'push/config', ['middleware' => ['wxlogger'], 'uses' => 'DeviceController@pushSet']);
    });
    Route::Group(['namespace' => 'Index', 'prefix' => 'index'], function () {
        Route::match(['post', 'get'], 'index/head', ['middleware' => ['wxlogger'], 'uses' => 'IndexDeviceController@headSearch']);
        Route::match(['post', 'get'], 'index/device', ['middleware' => ['wxlogger'], 'uses' => 'IndexDeviceController@deviceStatistics']);
        Route::match(['post', 'get'], 'index/warning', ['middleware' => ['wxlogger'], 'uses' => 'IndexDeviceController@warning']);
        Route::match(['post', 'get'], 'index/message', ['middleware' => ['wxlogger'], 'uses' => 'IndexDeviceController@message']);
        Route::match(['post', 'get'], 'message/detail', ['middleware' => ['wxlogger'], 'uses' => 'IndexDeviceController@detail']);
    });
    Route::Group(['namespace' => 'Work', 'prefix' => 'work'], function () {
        Route::match(['post', 'get'], 'warn/manage', ['middleware' => ['wxlogger'], 'uses' => 'WarnRecoverController@warnSearch']);
        Route::match(['post', 'get'], 'warn/get', ['middleware' => ['wxlogger'], 'uses' => 'WarnRecoverController@get']);
        Route::match(['post', 'get'], 'warn/deal', ['middleware' => ['wxlogger'], 'uses' => 'WarnRecoverController@dealWarn']);
    });
});

