<?php

use Illuminate\Support\Facades\Route;

Route::get('/mai', 'Mai\MaiPortalController@home')->name('mai.customer.home');
Route::post('/mai/register', 'Mai\MaiPortalController@register')->middleware('throttle:10,10')->name('mai.customer.register');
Route::post('/mai/login', 'Mai\MaiPortalController@login')->middleware('throttle:10,10')->name('mai.customer.login');
Route::get('/mai/verify/{token}', 'Mai\MaiPortalController@verify')->name('mai.customer.verify');
Route::post('/mai/logout', 'Mai\MaiPortalController@logout')->name('mai.customer.logout');
Route::post('/mai/sessions', 'Mai\MaiPortalController@createSession')->middleware('throttle:10,10')->name('mai.customer.sessions');
Route::post('/mai/sessions/{publicId}/pair', 'Mai\MaiPortalController@pair')->middleware('throttle:10,10')->name('mai.customer.pair');
Route::post('/mai/sessions/{publicId}/refresh', 'Mai\MaiPortalController@refresh')->middleware('throttle:30,1')->name('mai.customer.refresh');
Route::post('/mai/sessions/{publicId}/disconnect', 'Mai\MaiPortalController@disconnect')->middleware('throttle:10,10')->name('mai.customer.disconnect');
Route::post('/mai/sessions/{publicId}/send', 'Mai\MaiPortalController@send')->middleware('throttle:20,1')->name('mai.customer.send');
Route::post('/mai/keys', 'Mai\MaiPortalController@createKey')->middleware('throttle:10,10')->name('mai.customer.keys');
