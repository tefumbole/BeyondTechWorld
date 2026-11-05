<?php

use Illuminate\Support\Facades\Route;

Route::middleware('travel.enabled')->prefix('travel')->group(function () {
    Route::get('/', 'Travel\TravelController@home')->name('travel.home');
    Route::get('/book', 'Travel\TravelController@book')->name('travel.book');
    Route::get('/lang/{locale}', 'Travel\TravelController@lang')->name('travel.lang');
    Route::get('/departures/{id}', 'Travel\TravelController@departure')->name('travel.departure');
    Route::post('/otp', 'Travel\TravelController@otp')->middleware('throttle:8,1')->name('travel.otp');
    Route::post('/checkout', 'Travel\TravelController@checkout')->name('travel.checkout');
    Route::get('/pay/{id}', 'Travel\TravelController@pay')->name('travel.pay');
    Route::post('/pay/{id}', 'Travel\TravelController@charge')->name('travel.charge');
    Route::post('/payments/callback', 'Travel\TravelController@callback')->name('travel.callback');
    Route::get('/my-trips', 'Travel\TravelController@tripsForm')->name('travel.trips');
    Route::post('/my-trips', 'Travel\TravelController@trips')->name('travel.trips.unlock');
    Route::get('/track', 'Travel\TravelController@track')->name('travel.track');

    Route::middleware('auth')->prefix('staff')->group(function () {
        Route::get('/', 'Travel\TravelStaffController@hub')->name('travel.staff');
        Route::get('/dispatch', 'Travel\TravelStaffController@dispatchForm')->name('travel.dispatch');
        Route::post('/fixture', 'Travel\TravelStaffController@fixture')->name('travel.fixture');
        Route::post('/routes', 'Travel\TravelStaffController@route')->name('travel.routes');
        Route::post('/departures', 'Travel\TravelStaffController@schedule')->name('travel.schedule');
        Route::post('/departures/{id}/status', 'Travel\TravelStaffController@status')->name('travel.status');
        Route::get('/departures/{id}/manifest', 'Travel\TravelStaffController@manifest')->name('travel.manifest');
        Route::post('/board', 'Travel\TravelStaffController@board')->name('travel.board');
        Route::post('/bookings/{id}/noshow', 'Travel\TravelStaffController@noShow')->name('travel.noshow');
        Route::get('/counter', 'Travel\TravelStaffController@counter')->name('travel.counter');
        Route::post('/counter', 'Travel\TravelStaffController@sell')->name('travel.sell');
        Route::post('/bookings/{id}/void', 'Travel\TravelStaffController@void')->name('travel.void');
        Route::get('/parcels', 'Travel\TravelStaffController@parcels')->name('travel.parcels');
        Route::post('/parcels', 'Travel\TravelStaffController@storeParcel')->name('travel.parcels.store');
        Route::post('/parcels/{id}/scan', 'Travel\TravelStaffController@scan')->name('travel.parcels.scan');
        Route::post('/parcels/{id}/collect', 'Travel\TravelStaffController@collect')->name('travel.parcels.collect');
        Route::get('/reconciliation', 'Travel\TravelStaffController@reconcile')->name('travel.reconcile');
        Route::post('/reconciliation/close', 'Travel\TravelStaffController@closeTill')->name('travel.till.close');
    });
});
