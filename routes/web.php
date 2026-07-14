<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\SurveyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SurveyController::class, 'index'])->name('home');

Route::get('/obcina/{municipality}', [SurveyController::class, 'show'])->name('survey.show');
Route::post('/obcina/{municipality}', [SurveyController::class, 'store'])->name('survey.store');
Route::get('/hvala/{token}', [SurveyController::class, 'thanks'])->name('survey.thanks');

// Hidden auth routes (not linked anywhere in the public app).
Route::get('/prijava', [AuthController::class, 'login'])->name('login');
Route::get('/auth/keycloak', [AuthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/keycloak/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/odjava', [AuthController::class, 'logout'])->name('auth.logout');

Route::middleware('auth')->group(function () {
    Route::get('/rezultati', [ResultsController::class, 'index'])->name('results.index');
    Route::get('/rezultati/izvoz.csv', [ResultsController::class, 'export'])->name('results.export');
    Route::get('/rezultati/odgovor/{response}', [ResultsController::class, 'showResponse'])->name('results.response');
});