<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{GameController,PaymentController};
use App\Http\Middleware\IdentifyPlayer;
Route::post('/stripe/webhook',[PaymentController::class,'webhook']);
Route::middleware(IdentifyPlayer::class)->group(function() {
 Route::view('/','home');
 Route::get('/games/{game}',function(string $game){abort_unless(in_array($game,['hangman','blackjack','daily']),404);return view('arcade',['game'=>$game]);});
 Route::get('/api/status',[GameController::class,'status']);
 Route::post('/api/games/{game}',[GameController::class,'start'])->middleware('throttle:30,1')->block(10,10);
 Route::post('/api/rounds/{id}',[GameController::class,'move'])->middleware('throttle:120,1')->block(10,10);
 Route::post('/api/checkout',[PaymentController::class,'checkout'])->middleware('throttle:5,1')->block(10,10);
 Route::post('/api/restore',[PaymentController::class,'restore'])->middleware('throttle:5,1')->block(10,10);
 Route::get('/payment/return',[PaymentController::class,'returned']);
});
