<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Player,GameRoom,RoomSeat,GameInvite};
use App\Services\{Multiplayer as M,Blackjack as B};
class MultiplayerTest extends TestCase {
 use RefreshDatabase;
 private function guest(string $token): Player {$p=Player::firstOrCreate(['token_hash'=>hash('sha256',$token)]);$this->withCredentials()->withCookie('arcade_player',$token);$this->getJson('/api/social')->assertOk();return $p->fresh();}
 private function setupRoom(string $game='tictactoe'): array {$a=$this->guest('a');$code=$this->postJson('/api/rooms',['game'=>$game])->assertOk()->json('code');$b=$this->guest('b');$this->postJson("/api/rooms/$code/join")->assertOk();return [$code,$a,$b];}
 private function startRoom(string $code): array {$this->guest('b');$this->postJson("/api/rooms/$code/ready",['ready'=>true])->assertOk();$this->guest('a');$v=$this->postJson("/api/rooms/$code/ready",['ready'=>true])->assertOk()->json('version');return $this->postJson("/api/rooms/$code/start",['version'=>$v])->assertOk()->json();}
 public function test_names_invitations_acceptance_and_room_privacy(): void {
  $a=$this->guest('a');$this->assertSame($a->handle,$this->guest('a')->handle);$b=$this->guest('b');$this->assertNotSame($a->handle,$b->handle);
  $this->guest('a');$code=$this->postJson('/api/rooms',['game'=>'fade'])->json('code');$this->postJson("/api/rooms/$code/invite",['name'=>$b->handle])->assertOk();$i=GameInvite::first();$this->postJson('/api/invitations/'.$i->id,['accept'=>true])->assertNotFound();
  $this->guest('b');$this->getJson('/api/social')->assertJsonPath('invitations.0.id',$i->id);$this->assertSame(1,RoomSeat::count());$this->postJson('/api/invitations/'.$i->id,['accept'=>true])->assertOk();$this->postJson('/api/invitations/'.$i->id,['accept'=>true])->assertStatus(409);
  $this->startRoom($code);$this->guest('c');$this->getJson("/api/rooms/$code")->assertJsonPath('member',false)->assertJsonPath('state',null);$this->postJson("/api/rooms/$code/join")->assertStatus(409);$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>0,'action'=>'place','cell'=>0])->assertNotFound();
 }
 public function test_ready_consent_shared_quota_and_repeated_start(): void {
  [$code,$a,$b]=$this->setupRoom();$this->guest('a');$v=$this->getJson("/api/rooms/$code")->json('version');$this->postJson("/api/rooms/$code/start",['version'=>$v])->assertStatus(422);$this->assertSame(0,$a->fresh()->tictactoe_plays);
  $data=$this->startRoom($code);$this->assertSame(1,$a->fresh()->tictactoe_plays);$this->assertSame(1,$b->fresh()->tictactoe_plays);$this->postJson("/api/rooms/$code/start",['version'=>$data['version']])->assertStatus(409);$this->assertSame(1,$a->fresh()->tictactoe_plays);
  $this->postJson("/api/rooms/$code/leave")->assertOk();$this->guest('b');$b->update(['tictactoe_plays'=>5]);$this->postJson("/api/rooms/$code/ready",['ready'=>true])->assertStatus(402);$this->postJson('/api/games/tictactoe',[])->assertStatus(402);$b->update(['unlimited'=>true]);$this->postJson("/api/rooms/$code/ready",['ready'=>true])->assertOk();
 }
 public function test_turns_stale_moves_and_win(): void {
  [$code,$a,$b]=$this->setupRoom();$this->startRoom($code);$this->guest('b');$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>0,'action'=>'place','cell'=>0])->assertStatus(409);
  foreach([0,3,1,4,2] as $i=>$cell){$this->guest($i%2?'b':'a');$res=$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>$i,'action'=>'place','cell'=>$cell])->assertOk();}
  $res->assertJsonPath('status','finished')->assertJsonPath('state.winner',$a->id);$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>5,'action'=>'place','cell'=>8])->assertStatus(409);
  $this->startRoom($code);$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>0,'action'=>'place','cell'=>8])->assertStatus(409);
 }
 public function test_fade_draw_removes_oldest_two_per_side_without_charge(): void {
  [$code,$a,$b]=$this->setupRoom('fade');$this->startRoom($code);
  foreach([0,1,2,4,3,5,7,6,8] as $i=>$cell){$this->guest($i%2?'b':'a');$res=$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>$i,'action'=>'place','cell'=>$cell])->assertOk();}
  $res->assertJsonPath('status','playing')->assertJsonPath('state.cycles',1)->assertJsonPath('state.turn',$b->id);$this->assertSame([null,null,null,'X',null,'O','O','X','X'],$res->json('state.board'));$this->assertSame(1,$a->fresh()->fade_plays);$this->assertSame(1,$b->fresh()->fade_plays);
  $this->guest('b');$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>9,'action'=>'place','cell'=>0])->assertStatus(409);
 }
 public function test_blackjack_shared_shoe_and_deferred_dealer(): void {
  [$code,$a,$b]=$this->setupRoom('blackjack');$this->startRoom($code);$room=GameRoom::where('code',$code)->first();$s=$room->state;
  // Choose deterministic non-natural hands from one physical six-deck shoe.
  $shoe=B::shoe();$take=function($rank)use(&$shoe){foreach($shoe as $i=>$c)if($c['rank']===$rank){array_splice($shoe,$i,1);return $c;}};
  $s['dealer']=[$take('10'),$take('6')];foreach([$a,$b] as $p){$s['players'][$p->id]=['hands'=>[['cards'=>[$take('10'),$take('8')],'bet'=>10,'split'=>false,'done'=>false]],'dealer'=>$s['dealer'],'active'=>0,'status'=>'playing','revision'=>0,'deadline'=>now()->timestamp+120];}$s['shoe']=$shoe;$room->update(['state'=>$s,'status'=>'playing']);
  $view=$this->getJson("/api/rooms/$code")->assertOk()->json('state');$this->assertArrayNotHasKey('shoe',$view);$this->assertNull($view['dealer'][1]);$this->assertNull($view['players'][$a->id]['dealer'][1]);
  $this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>0,'action'=>'stand'])->assertOk()->assertJsonPath('status','playing')->assertJsonPath('state.dealer.1',null);
  $this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>0,'action'=>'hit'])->assertStatus(409);
  $this->guest('b');$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>0,'action'=>'stand'])->assertOk()->assertJsonPath('status','finished');$s=$room->fresh()->state;
  $cards=array_merge($s['shoe'],$s['dealer']);foreach($s['players'] as $p){$this->assertSame($s['dealer'],$p['dealer']);foreach($p['hands'] as $h)$cards=array_merge($cards,$h['cards']);}$this->assertCount(312,$cards);$this->assertCount(312,array_unique(array_column($cards,'id')));
 }
 public function test_timeouts_leave_and_host_transfer(): void {
  [$code,$a,$b]=$this->setupRoom();$this->startRoom($code);$this->travel(121)->seconds();$this->guest('b');$this->getJson("/api/rooms/$code")->assertJsonPath('status','finished')->assertJsonPath('state.winner',$b->id)->assertJsonPath('state.reason','Turn timed out');
  $this->guest('a');$this->postJson("/api/rooms/$code/leave")->assertOk();$this->guest('b');$this->getJson("/api/rooms/$code")->assertJsonPath('host_id',$b->id);$this->postJson('/api/rooms',['game'=>'fade'])->assertStatus(409);
  $this->travel(301)->seconds();$this->getJson('/api/social')->assertJsonPath('room',null);$this->assertSame('closed',GameRoom::where('code',$code)->first()->status);
 }
 public function test_blackjack_capacity_and_idle_hands_settle_automatically(): void {
  [$code,$a,$b]=$this->setupRoom('blackjack');foreach(['c','d','e'] as $token){$this->guest($token);$this->postJson("/api/rooms/$code/join")->assertOk();$this->postJson("/api/rooms/$code/ready",['ready'=>true])->assertOk();}
  $this->guest('f');$this->postJson("/api/rooms/$code/join")->assertStatus(409);$this->startRoom($code);$this->travel(121)->seconds();$this->guest('a');$this->getJson("/api/rooms/$code")->assertOk()->assertJsonPath('status','finished');$room=GameRoom::where('code',$code)->first();foreach($room->state['players'] as $p)$this->assertSame('finished',$p['status']);
 }
 public function test_declined_and_expired_invitations_do_not_join(): void {
  $a=$this->guest('a');$code=$this->postJson('/api/rooms',['game'=>'fade'])->json('code');$b=$this->guest('b');$this->guest('a');$this->postJson("/api/rooms/$code/invite",['name'=>$b->handle])->assertOk();$i=GameInvite::first();$this->guest('b');$this->postJson('/api/invitations/'.$i->id,['accept'=>false])->assertOk();$this->assertSame(1,RoomSeat::count());
  $i->update(['status'=>'pending','expires_at'=>now()->subSecond()]);$this->postJson('/api/invitations/'.$i->id,['accept'=>true])->assertStatus(409);$this->getJson('/api/social')->assertJsonCount(0,'invitations');
 }
}
