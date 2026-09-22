<?php
namespace App\Http\Controllers;
use App\Models\{Player,GameRoom,RoomSeat,GameInvite};
use App\Services\Multiplayer as M;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class MultiplayerController extends Controller {
 private function player(Request $r): Player {return $r->attributes->get('player');}
 private function locked(string $code): GameRoom {return GameRoom::where('code',$code)->lockForUpdate()->firstOrFail();}
 public function social(Request $r) {
  $p=$this->player($r);if(!$p->last_seen_at||$p->last_seen_at->lt(now()->subSeconds(15)))$p->update(['last_seen_at'=>now()]);
  $seat=RoomSeat::where('player_id',$p->id)->first();
  if($seat)DB::transaction(function()use($seat){$room=GameRoom::whereKey($seat->room_id)->lockForUpdate()->first();if($room)M::expire($room);},3);
  $seat=RoomSeat::where('player_id',$p->id)->first();$room=$seat?GameRoom::find($seat->room_id):null;
  $invitations=GameInvite::where('recipient_id',$p->id)->where('status','pending')->where('expires_at','>',now())->latest()->limit(20)->get();
  $invites=[];foreach($invitations as $i){$roomInvite=GameRoom::find($i->room_id);if($roomInvite&&in_array($roomInvite->status,['lobby','finished']))$invites[]=['id'=>$i->id,'from'=>Player::find($i->sender_id)->handle,'game'=>$roomInvite->game,'code'=>$roomInvite->code];}
  return ['me'=>['id'=>$p->id,'name'=>$p->handle],'room'=>$room?['code'=>$room->code,'game'=>$room->game]:null,'online'=>Player::where('id','!=',$p->id)->where('last_seen_at','>',now()->subSeconds(45))->whereNotNull('handle')->orderByDesc('last_seen_at')->limit(30)->pluck('handle'),'invitations'=>$invites];
 }
 public function create(Request $r) {
  $d=$r->validate(['size'=>'sometimes|integer|between:3,8','game'=>['required',Rule::in(M::GAMES)]]);$p=$this->player($r);
  return DB::transaction(function()use($p,$d){Player::whereKey($p->id)->lockForUpdate()->firstOrFail();abort_if(RoomSeat::where('player_id',$p->id)->exists(),409,'You already have a room. Return to it or leave it first.');$room=GameRoom::create(['code'=>Str::lower(Str::random(24)),'game'=>$d['game'],'host_id'=>$p->id,'status'=>'lobby','version'=>0,'dots_size'=>(int)($d['size']??3)]);M::join($room,$p);return M::view($room,$p);},3);
 }
 public function show(Request $r,string $code) {
  return DB::transaction(function()use($r,$code){$room=$this->locked($code);$p=$this->player($r);RoomSeat::where('room_id',$room->id)->where('player_id',$p->id)->update(['seen_at'=>now()]);M::expire($room);return M::view($room,$p);},3);
 }
 public function join(Request $r,string $code) {return DB::transaction(function()use($r,$code){$room=$this->locked($code);M::expire($room);$p=$this->player($r);M::join($room,$p);return M::view($room,$p);},3);}
 public function ready(Request $r,string $code) {
  $d=$r->validate(['ready'=>'required|boolean']);return DB::transaction(function()use($r,$code,$d){$room=$this->locked($code);M::expire($room);abort_if(!in_array($room->status,['lobby','finished']),409,'Wait for this round to finish.');$p=$this->player($r);$seat=M::seat($room,$p->id);abort_if($d['ready']&&!$p->unlimited&&$p->{$room->game.'_plays'}>=5,402,'Your five free plays are used. Unlock all games for $1.');$seat->update(['ready'=>$d['ready'],'seen_at'=>now()]);$room->version++;$room->save();return M::view($room,$p);},3);
 }
 public function start(Request $r,string $code) {$d=$r->validate(['version'=>'required|integer|min:0']);return DB::transaction(function()use($r,$code,$d){$room=$this->locked($code);M::expire($room);$p=$this->player($r);M::seat($room,$p->id);abort_unless($room->host_id===$p->id,403,'Only the host can start the round.');M::start($room,$d['version']);return M::view($room,$p);},3);}
 public function move(Request $r,string $code) {
  $d=$r->validate(['round'=>'required|integer|min:1','revision'=>'required|integer|min:0','action'=>['required',Rule::in(['line','place','hit','stand','double','split'])],'edge'=>'required_if:action,line|integer|between:0,143','cell'=>'required_if:action,place|integer|between:0,8']);
  return DB::transaction(function()use($r,$code,$d){$room=$this->locked($code);M::expire($room);$p=$this->player($r);M::move($room,$p->id,$d);return M::view($room,$p);},3);
 }
 public function leave(Request $r,string $code) {return DB::transaction(function()use($r,$code){$room=$this->locked($code);M::leave($room,$this->player($r)->id);return ['left'=>true];},3);}
 public function invite(Request $r,string $code) {
  $d=$r->validate(['name'=>'required|string|max:90']);return DB::transaction(function()use($r,$code,$d){$room=$this->locked($code);M::expire($room);$p=$this->player($r);M::seat($room,$p->id);abort_unless(in_array($room->status,['lobby','finished']),409,'Wait for the round to finish.');abort_if(M::seats($room)->count()>=M::capacity($room),409,'This room is full.');$target=Player::where('handle',strtolower(ltrim(trim($d['name']),'@')))->first();abort_unless($target,422,'No guest has that name. Ask them to copy their full guest name.');abort_if($target->id===$p->id,422,'Invite someone other than yourself.');$old=GameInvite::where('room_id',$room->id)->where('recipient_id',$target->id)->first();abort_if($old&&$old->updated_at->gt(now()->subMinutes(2)),429,'Please wait before inviting this guest again.');GameInvite::updateOrCreate(['room_id'=>$room->id,'recipient_id'=>$target->id],['sender_id'=>$p->id,'status'=>'pending','expires_at'=>now()->addMinutes(10)]);return ['sent'=>true];},3);
 }
 public function respond(Request $r,string $id) {
  $d=$r->validate(['accept'=>'required|boolean']);$p=$this->player($r);$invite=GameInvite::where('recipient_id',$p->id)->findOrFail($id);
  return DB::transaction(function()use($invite,$p,$d){$room=GameRoom::whereKey($invite->room_id)->lockForUpdate()->firstOrFail();$i=GameInvite::whereKey($invite->id)->lockForUpdate()->firstOrFail();abort_unless($i->status==='pending'&&$i->expires_at->isFuture(),409,'This invitation has expired or was already answered.');if($d['accept']){M::expire($room);M::join($room,$p);}$i->update(['status'=>$d['accept']?'accepted':'declined']);return ['code'=>$d['accept']?$room->code:null];},3);
 }
}
