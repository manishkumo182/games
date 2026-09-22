<?php
namespace App\Services;
use App\Models\{GameRoom,RoomSeat,Player};
use Illuminate\Support\Facades\DB;
class Multiplayer {
 public const GAMES=['tictactoe','fade','dots','blackjack'];
 public static function seats(GameRoom $room) {return RoomSeat::where('room_id',$room->id)->orderBy('id')->get();}
 public static function capacity(GameRoom $room): int {return $room->game==='blackjack'?5:2;}
 public static function seat(GameRoom $room,string $id): RoomSeat {return RoomSeat::where('room_id',$room->id)->where('player_id',$id)->firstOrFail();}
 public static function join(GameRoom $room,Player $player): void {
  Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
  $old=RoomSeat::where('player_id',$player->id)->first();
  if($old&&$old->room_id===$room->id){$old->update(['seen_at'=>now()]);return;}
  abort_if($old,409,'You already have a room. Leave it before joining another.');
  abort_unless(in_array($room->status,['lobby','finished']),409,'This round is already in progress or the room has closed.');
  abort_if(self::seats($room)->count()>=self::capacity($room),409,'This room is full.');
  RoomSeat::create(['room_id'=>$room->id,'player_id'=>$player->id,'seen_at'=>now()]);$room->increment('version');
 }
 public static function start(GameRoom $room,int $version): void {
  abort_unless(in_array($room->status,['lobby','finished'])&&$room->version===$version,409,'The room changed. Please refresh and try again.');
  $seats=self::seats($room);abort_unless($seats->count()>=2,422,'At least two players are needed.');
  abort_if($seats->contains(fn($s)=>!$s->ready||$s->seen_at->lt(now()->subSeconds(60))),422,'Everyone must be online and ready.');
  $players=Player::whereIn('id',$seats->pluck('player_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
  foreach($players as $p){$field=$room->game.'_plays';abort_if(!$p->unlimited&&$p->$field>=5,402,$p->handle.' has used all five free plays.');}
  $ids=$seats->pluck('player_id')->all();$names=[];foreach($ids as $id){$p=$players[$id];$field=$room->game.'_plays';$p->$field++;$p->save();$names[$id]=$p->handle;}
  $s=['ids'=>$ids,'names'=>$names,'round'=>($room->state['round']??0)+1,'revision'=>0,'reason'=>null,'winner'=>null];
  if($room->game==='blackjack'){
   $s['shoe']=Blackjack::shoe();$s['dealer']=[];$s['players']=[];
   foreach($ids as $id)$s['players'][$id]=['hands'=>[['cards'=>[],'bet'=>10,'split'=>false,'done'=>false]],'dealer'=>[],'active'=>0,'status'=>'playing','revision'=>0,'deadline'=>now()->timestamp+120];
   for($i=0;$i<2;$i++){foreach($ids as $id)$s['players'][$id]['hands'][0]['cards'][]=array_pop($s['shoe']);$s['dealer'][]=array_pop($s['shoe']);}
   foreach($ids as $id){$s['players'][$id]['dealer']=$s['dealer'];if(Blackjack::total($s['players'][$id]['hands'][0]['cards'])===21){$s['players'][$id]['hands'][0]['done']=true;$s['players'][$id]['status']='waiting';}}
   if(Blackjack::total($s['dealer'])===21)foreach($ids as $id){$s['players'][$id]['status']='waiting';$s['players'][$id]['hands'][0]['done']=true;}
  }else{
   $s=array_merge($s,$room->game==='dots'?DotsAndBoxes::start($room->dots_size??3):($room->game==='fade'?FadeTacToe::start():TicTacToe::start()));$s['turn']=$ids[0];$s['deadline']=now()->timestamp+120;$s['resume_at']=0;
  }
  $room->status='playing';$room->state=$s;$room->version++;$room->save();
  RoomSeat::where('room_id',$room->id)->update(['ready'=>false]);
  if($room->game==='blackjack')self::finishBlackjack($room);
 }
 public static function finishBlackjack(GameRoom $room): void {
  $s=$room->state;foreach($s['players'] as $p)if($p['status']==='playing')return;
  $live=false;foreach($s['players'] as $p)foreach($p['hands'] as $h)if(Blackjack::total($h['cards'])<=21)$live=true;
  if($live)while(Blackjack::total($s['dealer'])<17)$s['dealer'][]=array_pop($s['shoe']);
  foreach($s['players'] as &$p){$p['dealer']=$s['dealer'];$p=Blackjack::settle($p);}unset($p);
  $room->state=$s;$room->status='finished';$room->version++;$room->save();
 }
 public static function move(GameRoom $room,string $id,array $data): void {
  self::seat($room,$id);abort_unless($room->status==='playing',409,'This round has finished.');$s=$room->state;
  abort_unless(($data['round']??null)===$s['round'],409,'That move belongs to an earlier round.');
  if($room->game==='blackjack'){
   $p=$s['players'][$id]??null;abort_unless($p&&$p['status']==='playing'&&$p['revision']===$data['revision'],409,'Your hand changed. Please try again.');
   $p=Blackjack::act($p,$data['action'],$s['shoe'],true);$p['revision']++;$p['deadline']=now()->timestamp+120;$s['players'][$id]=$p;
  }else{
   abort_unless($s['turn']===$id&&$s['revision']===$data['revision'],409,'It is not your turn, or the board has changed.');
   abort_if(($s['resume_at']??0)>microtime(true),409,'The old marks are still fading.');
   if($room->game==='dots'){
    abort_unless($data['action']==='line'&&isset($data['edge']),422,'Choose a line.');
    $s=DotsAndBoxes::place($s,$data['edge'],$id===$s['ids'][0]?'X':'O');
   }else{
   abort_unless($data['action']==='place'&&isset($data['cell']),422,'Choose a square.');$cell=$data['cell'];
   abort_unless($cell>=0&&$cell<9&&$s['board'][$cell]===null,422,'Choose an empty square.');$mark=$id===$s['ids'][0]?'X':'O';
   if($room->game==='fade'){$s['events']=[];$s=FadeTacToe::place($s,$cell,$mark);$faded=count(array_filter($s['events'],fn($e)=>$e['type']==='fade'))>0;$s['resume_at']=$faded?microtime(true)+2:0;}
   else{$s['board'][$cell]=$mark;$s=array_merge($s,TicTacToe::outcome($s['board']));}
   }
   $s['revision']++;
   if($s['status']==='playing'){$s['turn']=$room->game==='dots'?($s['next']==='X'?$s['ids'][0]:$s['ids'][1]):($id===$s['ids'][0]?$s['ids'][1]:$s['ids'][0]);$s['deadline']=now()->timestamp+120;}
   else{$room->status='finished';$s['winner']=$s['status']==='draw'?null:($s['status']==='won'?$s['ids'][0]:$s['ids'][1]);$s['turn']=null;}
  }
  $room->state=$s;$room->version++;$room->save();if($room->game==='blackjack')self::finishBlackjack($room);
 }
 public static function expire(GameRoom $room): void {
  if($room->status==='playing'){
   $s=$room->state;
   if($room->game==='blackjack'){
    $changed=false;foreach($s['players'] as &$p)if($p['status']==='playing'&&$p['deadline']<=now()->timestamp){foreach($p['hands'] as &$h)$h['done']=true;unset($h);$p['status']='waiting';$p['timed_out']=true;$p['revision']++;$changed=true;}unset($p);
    if($changed){$room->state=$s;$room->version++;$room->save();self::finishBlackjack($room);}
   }elseif($s['deadline']<=now()->timestamp){$s['winner']=$s['turn']===$s['ids'][0]?$s['ids'][1]:$s['ids'][0];$s['reason']='Turn timed out';$s['turn']=null;$room->state=$s;$room->status='finished';$room->version++;$room->save();}
  }
  if(in_array($room->status,['lobby','finished'])){
   $removed=RoomSeat::where('room_id',$room->id)->where('seen_at','<',now()->subMinutes(5))->delete();
   if($removed){$room->version++;$room->save();}
   $seats=self::seats($room);
   if($seats->isEmpty()){$room->status='closed';$room->save();}
   elseif(!$seats->contains('player_id',$room->host_id)){$room->host_id=$seats->first()->player_id;$room->version++;$room->save();}
  }
 }
 public static function leave(GameRoom $room,string $id): void {
  $seat=self::seat($room,$id);
  if($room->status==='playing'){
   $s=$room->state;
   if($room->game==='blackjack'){$p=$s['players'][$id];foreach($p['hands'] as &$h)$h['done']=true;unset($h);if($p['status']==='playing')$p['status']='waiting';$p['left']=true;$s['players'][$id]=$p;$room->state=$s;$room->save();self::finishBlackjack($room);}
   else{$s['winner']=$id===$s['ids'][0]?$s['ids'][1]:$s['ids'][0];$s['reason']='Opponent left the room';$s['turn']=null;$room->state=$s;$room->status='finished';$room->save();}
  }
  $seat->delete();$left=self::seats($room);if($left->isEmpty())$room->status='closed';elseif($room->host_id===$id)$room->host_id=$left->first()->player_id;$room->version++;$room->save();
 }
 public static function view(GameRoom $room,Player $player): array {
  $seats=self::seats($room);$member=$seats->contains('player_id',$player->id);$names=Player::whereIn('id',$seats->pluck('player_id'))->pluck('handle','id');
  $out=['size'=>$room->dots_size??3,'code'=>$room->code,'game'=>$room->game,'status'=>$room->status,'version'=>$room->version,'host_id'=>$room->host_id,'member'=>$member,'capacity'=>self::capacity($room),'me'=>$player->id,'access'=>$player->fresh()->access(),'seats'=>$seats->map(fn($s)=>['id'=>$s->player_id,'name'=>$names[$s->player_id],'ready'=>$s->ready,'online'=>$s->seen_at->gt(now()->subSeconds(20))])->all(),'state'=>null,'server_now'=>microtime(true)];
  if(!$member||!$room->state)return $out;
  $s=$room->state;unset($s['shoe']);
  if($room->game==='blackjack'){
   foreach($s['players'] as $id=>&$p){$p=Blackjack::view($p);if($id!==$player->id)$p['actions']=[];}unset($p);
   if($room->status==='playing')$s['dealer']=[$s['dealer'][0],null];
  }
  $out['state']=$s;return $out;
 }
}
