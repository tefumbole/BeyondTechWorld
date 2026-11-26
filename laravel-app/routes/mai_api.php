<?php

use Illuminate\Support\Facades\Route;

Route::prefix('mai/v1')->group(function () {
    Route::get('/capabilities', 'Mai\MaiApiController@capabilities')->middleware('mai.key:sessions');
    Route::post('/sessions', 'Mai\MaiApiController@createSession')->middleware('mai.key:sessions');
    Route::get('/sessions/{publicId}', 'Mai\MaiApiController@show')->middleware('mai.key:sessions');
    Route::post('/sessions/{publicId}/pair', 'Mai\MaiApiController@pair')->middleware('mai.key:sessions');
    Route::post('/sessions/{publicId}/disconnect', 'Mai\MaiApiController@disconnect')->middleware('mai.key:sessions');
    Route::post('/sessions/{publicId}/messages', 'Mai\MaiApiController@send')->middleware('mai.key:messages');
    Route::get('/sessions/{publicId}/messages/{idempotencyKey}', 'Mai\MaiApiController@messageStatus')->middleware('mai.key:messages');
    Route::post('/webhooks', 'Mai\MaiApiController@webhook')->middleware('mai.key:webhooks');
    Route::delete('/keys/{id}', 'Mai\MaiApiController@revoke')->middleware('mai.key:sessions');
});
