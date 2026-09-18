<?php
namespace App\Http\Middleware;
use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
class IdentifyPlayer {
 public function handle(Request $request, Closure $next) {
  $token=$request->cookie('arcade_player');
  $player=$token ? Player::where('token_hash',hash('sha256',$token))->first() : null;
  if (!$player) { $token=Str::random(64); $player=Player::create(['token_hash'=>hash('sha256',$token)]); }
  if (!$player->handle) {
   $adjectives=['wobbly','cosmic','sleepy','sassy','dancing','bouncy','fuzzy','sneaky','jolly','zippy'];
   $nouns=['waffle','penguin','pickle','potato','badger','noodle','muffin','panda','taco','otter'];
   $handle=$adjectives[random_int(0,9)].'-'.$nouns[random_int(0,9)].'-'.substr(str_replace('-','',$player->id),-12);
   Player::whereKey($player->id)->whereNull('handle')->update(['handle'=>$handle]);
   $player->refresh();
  }
  Cookie::queue(cookie('arcade_player',$token,525600,null,null,config('session.secure'),true,false,'lax'));
  $request->attributes->set('player',$player);
  $response=$next($request);
  $response->headers->set('Cache-Control','no-store, private');
  return $response;
 }
}
