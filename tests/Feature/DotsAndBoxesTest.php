<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Player,Round,GameRoom};
use App\Services\{DotsAndBoxes as D,Multiplayer as M};
class DotsAndBoxesTest extends TestCase {
 use RefreshDatabase;
 private function guest(string $token='dots'): Player {$p=Player::firstOrCreate(['token_hash'=>hash('sha256',$token)]);$this->withCredentials()->withCookie('arcade_player',$token);return $p;}
 public function test_double_box_claim_keeps_turn_and_finishes_by_score(): void {
  $s=D::start();foreach([0,3,12,1,4,14] as $e)$s['edges'][$e]='O';$s=D::place($s,13,'X');$this->assertSame(2,$s['scores']['X']);$this->assertSame('X',$s['next']);$this->assertSame(['X','X'],array_slice($s['boxes'],0,2));
  while($s['status']==='playing'){$edge=array_keys($s['edges'],null,true)[0];$s=D::place($s,$edge,$s['next']);}
  $this->assertSame(9,array_sum($s['scores']));$this->assertNotContains(null,$s['edges']);$this->assertSame($s['scores']['X']>$s['scores']['O']?'won':'lost',$s['status']);
 }
 public function test_solo_page_resume_ownership_and_quota(): void {
  $p=$this->guest();$this->get('/games/dots')->assertOk()->assertSee('Dots and Boxes');$this->get('/')->assertSee('/games/dots');$a=$this->postJson('/api/games/dots',[])->assertOk()->assertJsonPath('access.dots',4);$id=$a->json('id');$this->postJson('/api/games/dots',[])->assertJsonPath('id',$id)->assertJsonPath('access.dots',4);
  $this->postJson('/api/rounds/'.$id,['action'=>'line','edge'=>0,'revision'=>0])->assertOk()->assertJsonPath('state.edges.0','X')->assertJsonPath('state.next','X');$this->postJson('/api/rounds/'.$id,['action'=>'line','edge'=>1,'revision'=>0])->assertStatus(422);$this->postJson('/api/rounds/'.$id,['action'=>'line','edge'=>0,'revision'=>1])->assertStatus(422);
  $this->guest('other');$this->postJson('/api/rounds/'.$id,['action'=>'line','edge'=>1,'revision'=>1])->assertNotFound();$this->guest();$r=Round::find($id);$s=$r->state;$s['status']='won';$r->update(['state'=>$s]);$p->update(['dots_plays'=>5]);$this->postJson('/api/games/dots',[])->assertStatus(402);$p->update(['unlimited'=>true]);$this->postJson('/api/games/dots',[])->assertOk();
 }
 public function test_computer_claims_and_repeats_until_turn_changes(): void {
  for($n=0;$n<100;$n++){$s=D::start();$moves=0;while($s['status']==='playing'){$s=D::move($s,array_keys($s['edges'],null,true)[0],$s['revision']);$this->assertTrue(++$moves<=24);if($s['status']==='playing')$this->assertSame('X',$s['next']);}$this->assertSame(9,array_sum($s['scores']));}
  $s=D::start();foreach([0,3,12] as $e)$s['edges'][$e]='X';$s['next']='O';$this->assertSame(13,D::computer($s));
 }
 public function test_multiplayer_extra_turn_and_shared_quota(): void {
  $a=$this->guest('a');$code=$this->postJson('/api/rooms',['game'=>'dots'])->assertOk()->json('code');$b=$this->guest('b');$this->postJson("/api/rooms/$code/join")->assertOk();$this->postJson("/api/rooms/$code/ready",['ready'=>true])->assertOk();$this->guest('a');$v=$this->postJson("/api/rooms/$code/ready",['ready'=>true])->json('version');$this->postJson("/api/rooms/$code/start",['version'=>$v])->assertOk();
  foreach([0,3,12,13] as $i=>$edge){$this->guest($i%2?'b':'a');$res=$this->postJson("/api/rooms/$code/move",['action'=>'line','edge'=>$edge,'revision'=>$i,'round'=>1])->assertOk();}
  $res->assertJsonPath('state.scores.O',1)->assertJsonPath('state.turn',$b->id);$this->guest('a');$this->postJson("/api/rooms/$code/move",['action'=>'line','edge'=>1,'revision'=>4,'round'=>1])->assertStatus(409);$this->assertSame(1,$a->fresh()->dots_plays);$this->assertSame(1,$b->fresh()->dots_plays);
 }
 public function test_all_sizes_conserve_edges_and_boxes(): void {
  for($size=3;$size<=8;$size++){$s=D::start($size);$this->assertCount(2*$size*($size+1),$s['edges']);$this->assertCount($size*$size,$s['boxes']);while($s['status']==='playing'){$s=D::move($s,array_keys($s['edges'],null,true)[0],$s['revision']);}$this->assertSame($size*$size,array_sum($s['scores']));$this->assertNotContains(null,$s['boxes']);}
  $s=D::start(4);$s['edges']=array_fill(0,40,'X');$s['edges'][39]=null;$s['boxes']=array_fill(0,16,'X');$s['boxes'][15]=null;$s['scores']=['X'=>7,'O'=>8];$this->assertSame('draw',D::place($s,39,'X')['status']);
 }
 public function test_size_validation_resume_and_room_setting(): void {
  $this->guest();$this->postJson('/api/games/dots',['size'=>9])->assertStatus(422);$a=$this->postJson('/api/games/dots',['size'=>8])->assertOk()->assertJsonPath('state.size',8)->assertJsonCount(64,'state.boxes');$this->postJson('/api/games/dots',['size'=>3])->assertJsonPath('id',$a->json('id'))->assertJsonPath('state.size',8)->assertJsonPath('access.dots',4);
  $this->postJson('/api/rooms',['game'=>'dots','size'=>2])->assertStatus(422);$code=$this->postJson('/api/rooms',['game'=>'dots','size'=>8])->assertOk()->assertJsonPath('size',8)->json('code');$this->postJson("/api/rooms/$code/ready",['ready'=>true])->assertOk();$this->guest('friend');$this->postJson("/api/rooms/$code/join")->assertJsonPath('size',8);$v=$this->postJson("/api/rooms/$code/ready",['ready'=>true])->json('version');$this->guest();$this->postJson("/api/rooms/$code/start",['version'=>$v])->assertOk()->assertJsonPath('state.size',8)->assertJsonCount(64,'state.boxes');$this->postJson("/api/rooms/$code/move",['round'=>1,'revision'=>0,'action'=>'line','edge'=>143])->assertOk()->assertJsonPath('state.edges.143','X');
 }
}
