<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\VenueBookings\Http\Controllers\BookingApiController;
use Modules\VenueBookings\Http\Controllers\BookingCalendarController;
use Modules\VenueBookings\Http\Controllers\BookingController;

/*
|--------------------------------------------------------------------------
| VenueBookings Module Web Routes
|--------------------------------------------------------------------------
*/

Route::prefix('reservas')->name('bookings.')->group(function (): void {
    Route::get('/', [BookingCalendarController::class, 'index'])->name('index');
    Route::get('/api/slots', [BookingApiController::class, 'slots'])->name('api.slots');
    Route::get('/api/events', [BookingApiController::class, 'calendarEvents'])->name('api.events');
    Route::get('/api/bookings/{booking}', [BookingApiController::class, 'show'])->name('api.show');

    Route::middleware('auth')->group(function (): void {
        Route::get('/api/eligibility', [BookingApiController::class, 'eligibility'])->name('api.eligibility');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('/mis-reservas', [BookingController::class, 'myBookings'])->name('my-bookings');
        Route::delete('/{booking}', [BookingController::class, 'cancel'])->name('cancel');
    });
});
