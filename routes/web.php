<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{GameController,PaymentController,MultiplayerController};
use App\Http\Middleware\IdentifyPlayer;
Route::post('/stripe/webhook',[PaymentController::class,'webhook']);
Route::middleware(IdentifyPlayer::class)->group(function() {
 Route::view('/','home');
 Route::view('/multiplayer','multiplayer');
 Route::get('/rooms/{code}',function(string $code){abort_unless(\App\Models\GameRoom::where('code',$code)->exists(),404);return view('multiplayer',['roomCode'=>$code]);});
 Route::get('/api/social',[MultiplayerController::class,'social'])->middleware('throttle:social-read');
 Route::post('/api/rooms',[MultiplayerController::class,'create'])->middleware('throttle:room-create')->block(10,10);
 Route::get('/api/rooms/{code}',[MultiplayerController::class,'show'])->middleware('throttle:room-read');
 foreach(['join','ready','start','move','leave','invite'] as $action) Route::post('/api/rooms/{code}/'.$action,[MultiplayerController::class,$action])->middleware($action==='invite'?'throttle:room-invite':'throttle:room-write')->block(10,10);
 Route::post('/api/invitations/{id}',[MultiplayerController::class,'respond'])->middleware('throttle:room-write')->block(10,10);

 Route::get('/games/{game}',function(string $game){abort_unless(in_array($game,['hangman','blackjack','daily','tictactoe','fade']),404);return view('arcade',['game'=>$game]);});
 Route::get('/api/status',[GameController::class,'status']);
 Route::post('/api/games/{game}',[GameController::class,'start'])->middleware('throttle:30,1')->block(10,10);
 Route::post('/api/rounds/{id}',[GameController::class,'move'])->middleware('throttle:120,1')->block(10,10);
 Route::post('/api/checkout',[PaymentController::class,'checkout'])->middleware('throttle:5,1')->block(10,10);
 Route::post('/api/restore',[PaymentController::class,'restore'])->middleware('throttle:5,1')->block(10,10);
 Route::get('/payment/return',[PaymentController::class,'returned']);
});
