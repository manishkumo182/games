<?php
namespace App\Http\Controllers;
use App\Models\{Player,Round};
use App\Services\{Hangman,Blackjack,DailyWord,TicTacToe,FadeTacToe,DotsAndBoxes};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class GameController extends Controller {
 private function output(Round $round,Player $player): array {
  return ['id'=>$round->id,'game'=>$round->game,'state'=>match($round->game) {'hangman'=>Hangman::view($round->state),'daily'=>DailyWord::view($round->state),'tictactoe','fade','dots'=>$round->state,default=>Blackjack::view($round->state)},'access'=>$player->access()];
 }
 public function status(Request $r) {
  $p=$r->attributes->get('player'); $rounds=[];
  foreach(['hangman','blackjack','daily','tictactoe','fade','dots'] as $game) { $round=Round::where('player_id',$p->id)->where('game',$game)->when($game==='daily',fn($q)=>$q->where('puzzle_date',DailyWord::date()))->latest()->orderByDesc('id')->first(); if($round) $rounds[$game]=$this->output($round,$p); }
  return ['daily'=>DailyWord::calendar(),'access'=>$p->access(),'rounds'=>$rounds,'checkout_available'=>(bool)config('services.stripe.secret'),'recovery_code'=>$p->unlimited ? $p->recovery_secret : null];
 }
 public function start(Request $r,string $game) {
  abort_unless(in_array($game,['hangman','blackjack','daily','tictactoe','fade','dots']),404);
  $data=$r->validate(['size'=>'sometimes|integer|between:3,8','difficulty'=>['sometimes',Rule::in(['easy','medium','hard'])]]);
  return DB::transaction(function() use($r,$game,$data) {
   $p=Player::lockForUpdate()->findOrFail($r->attributes->get('player')->id);
   $existing=Round::where('player_id',$p->id)->where('game',$game)->when($game==='daily',fn($q)=>$q->where('puzzle_date',DailyWord::date()))->latest()->orderByDesc('id')->first();
   // Retries and multiple tabs resume an unfinished round without using another free play.
   if($existing && ($game==='daily'||$existing->state['status']==='playing')) return $this->output($existing,$p);
   $counter=$game.'_plays'; abort_if(!$p->unlimited && $p->$counter>=5,402,'Your five free rounds are complete. Unlock all games for $1.');
   $p->$counter++;
   if($game==='hangman') $state=Hangman::start($data['difficulty'] ?? 'easy');
   elseif($game==='dots') $state=DotsAndBoxes::start((int)($data['size']??3));
   elseif($game==='fade') $state=FadeTacToe::start();
   elseif($game==='tictactoe') $state=TicTacToe::start();
   elseif($game==='daily') $state=DailyWord::start();
   else { $shoe=$p->shoe ?? []; $state=Blackjack::start($shoe); $p->shoe=$shoe; }
   $p->save(); $round=Round::create(['player_id'=>$p->id,'game'=>$game,'puzzle_date'=>$game==='daily'?DailyWord::date():null,'state'=>$state]);
   return $this->output($round,$p);
  },3);
 }
 public function move(Request $r,string $id) {
  $data=$r->validate(['action'=>['required',Rule::in(['line','place','word','guess','hit','stand','double','split'])],'edge'=>['required_if:action,line','integer','between:0,143'],'cell'=>['required_if:action,place','integer','between:0,8'],'revision'=>['required_if:action,place,line','integer','min:0'],'word'=>['required_if:action,word','string','regex:/^[A-Za-z]{5}$/'],'letter'=>['required_if:action,guess','string','regex:/^[A-Za-z]$/']]);
  return DB::transaction(function() use($r,$id,$data) {
   $p=Player::lockForUpdate()->findOrFail($r->attributes->get('player')->id);
   $round=Round::where('player_id',$p->id)->findOrFail($id); $state=$round->state;
   abort_unless($state['status']==='playing',409,'This round has finished. Start a new round.');
   if($round->game==='hangman') { abort_unless($data['action']==='guess',422); $state=Hangman::guess($state,strtoupper($data['letter'])); }
   elseif($round->game==='dots') {abort_unless($data['action']==='line',422);$state=DotsAndBoxes::move($state,(int)$data['edge'],(int)$data['revision']);}
   elseif($round->game==='fade') { abort_unless($data['action']==='place',422);$state=FadeTacToe::move($state,(int)$data['cell'],(int)$data['revision']); }
   elseif($round->game==='tictactoe') { abort_unless($data['action']==='place',422);$state=TicTacToe::move($state,(int)$data['cell'],(int)$data['revision']); }
   elseif($round->game==='daily') { abort_unless($state['date']===DailyWord::date(),409,'A new daily puzzle is ready. Start today’s puzzle.');abort_unless($data['action']==='word',422);$state=DailyWord::guess($state,strtoupper($data['word'])); }
   else { $shoe=$p->shoe; $state=Blackjack::act($state,$data['action'],$shoe); $p->shoe=$shoe; $p->save(); }
   $round->state=$state; $round->save(); return $this->output($round,$p);
  },3);
 }
}
