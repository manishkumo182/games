<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Player,Round};
use App\Services\FadeTacToe as F;
class FadeTacToeTest extends TestCase {
 use RefreshDatabase;
 private function nearDraw(): array {
  $s=F::start();foreach([[0,'X'],[1,'O'],[2,'X'],[4,'O'],[3,'X'],[5,'O'],[7,'X'],[6,'O']] as [$i,$m])$s=F::place($s,$i,$m);return $s;
 }
 public function test_oldest_two_of_each_fade_and_survivor_order_is_preserved(): void {
  $s=F::place($this->nearDraw(),8,'X');
  $this->assertSame('playing',$s['status']);$this->assertSame(1,$s['cycles']);
  $this->assertSame([null,null,null,'X',null,'O','O','X','X'],$s['board']);
  $this->assertSame([3,5,7,6,8],array_column($s['history'],'cell'));
  $this->assertSame([0,1,2,4],end($s['events'])['removed']);
  foreach([[4,'O'],[0,'X'],[1,'O'],[2,'X']] as [$i,$m])$s=F::place($s,$i,$m);
  $this->assertSame(2,$s['cycles']);$this->assertSame('playing',$s['status']);
  $this->assertSame([3,5,7,6],end($s['events'])['removed']);
  $this->assertSame(['X','O','X',null,'O',null,null,null,'X'],$s['board']);
 }
 public function test_full_board_win_ends_without_fading(): void {
  $s=F::start();$s['board']=['X','X',null,'O','O','X','X','O','O'];$s=F::place($s,2,'X');
  $this->assertSame('won',$s['status']);$this->assertSame(0,$s['cycles']);$this->assertSame([0,1,2],$s['winning_line']);
  $this->assertCount(0,array_filter($s['events'],fn($e)=>$e['type']==='fade'));
 }
 public function test_game_api_resumes_and_computer_continues_after_fade_without_extra_charge(): void {
  $p=Player::create(['token_hash'=>hash('sha256','fade-test')]);$this->withCredentials()->withCookie('arcade_player','fade-test');
  $this->get('/games/fade')->assertOk()->assertSee('Fade Tac Toe');$this->get('/')->assertSee('/games/fade');
  $a=$this->postJson('/api/games/fade',[])->assertOk()->assertJsonPath('access.fade',4);$id=$a->json('id');
  Round::find($id)->update(['state'=>$this->nearDraw()]);
  $res=$this->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>8,'revision'=>0])->assertOk()->assertJsonPath('state.cycles',1)->assertJsonPath('state.revision',1)->assertJsonPath('access.fade',4);
  $this->assertCount(6,array_filter($res->json('state.board'),fn($m)=>$m!==null));
  $this->assertSame('fade',$res->json('state.events.1.type'));$this->assertSame('place',$res->json('state.events.2.type'));
  $this->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>0,'revision'=>0])->assertStatus(422);
  $this->postJson('/api/games/fade',[])->assertJsonPath('id',$id)->assertJsonPath('access.fade',4);
  $this->getJson('/api/status')->assertJsonPath('rounds.fade.state.cycles',1);
 }
 public function test_independent_quota_and_existing_pass(): void {
  $p=Player::create(['token_hash'=>hash('sha256','limit-test'),'fade_plays'=>5]);$this->withCredentials()->withCookie('arcade_player','limit-test');
  $this->postJson('/api/games/fade',[])->assertStatus(402);$this->postJson('/api/games/tictactoe',[])->assertOk();
  $p->update(['unlimited'=>true]);$this->postJson('/api/games/fade',[])->assertOk();
 }
}
