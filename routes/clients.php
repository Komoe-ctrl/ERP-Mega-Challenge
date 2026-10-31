<?php

use App\Http\Controllers\Clients\ClientController;
use Illuminate\Support\Facades\Route;

// M2 — Clients : fiche, contacts, documents. Droits : ClientPolicy (`clients.*`).
Route::middleware(['auth', 'verified', 'can:clients.view'])->prefix('clients')->name('clients.')->group(function () {
    Route::get('/', [ClientController::class, 'index'])->name('index');
    Route::post('/', [ClientController::class, 'store'])->name('store');
    Route::get('{client}', [ClientController::class, 'show'])->whereNumber('client')->name('show');
    Route::put('{client}', [ClientController::class, 'update'])->whereNumber('client')->name('update');
    Route::post('{client}/contacts', [ClientController::class, 'storeContact'])->whereNumber('client')->name('contacts.store');
    Route::delete('{client}/contacts/{contact}', [ClientController::class, 'destroyContact'])->whereNumber('client')->whereNumber('contact')->name('contacts.destroy');
    Route::post('{client}/documents', [ClientController::class, 'storeDocument'])->whereNumber('client')->name('documents.store');
    Route::get('{client}/documents/{media}', [ClientController::class, 'document'])->whereNumber('client')->whereNumber('media')->name('documents.show');
    Route::delete('{client}/documents/{media}', [ClientController::class, 'destroyDocument'])->whereNumber('client')->whereNumber('media')->name('documents.destroy');
});
