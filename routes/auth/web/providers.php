<?php

Route::group(['prefix' => '/providers'], function () {
    Route::get('/create', 'Providers@create')->name('providers.create');

    Route::post('/', 'Providers@store')->name('providers.store');

    Route::get('/{id}', 'Providers@show')->name('providers.show');

    Route::post('/{id}', 'Providers@update')->name('providers.update');

    Route::get('/', 'Providers@index')->name('providers.index');
});
