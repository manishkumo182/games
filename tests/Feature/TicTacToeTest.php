<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Player,Round};
use App\Services\TicTacToe as T;
class TicTacToeTest extends TestCase {
 use RefreshDatabase;
 private function guest(): Player {$p=Player::create(['token_hash'=>hash('sha256','ttt-test')]);$this->withCredentials()->withCookie('arcade_player','ttt-test');return $p;}
 public function test_page_and_saved_round_with_computer_reply(): void {
  $this->guest();$this->get('/')->assertSee('/games/tictactoe');$this->get('/games/tictactoe')->assertOk()->assertSee('Tic Tac Toe');
  $a=$this->postJson('/api/games/tictactoe',[])->assertOk()->assertJsonPath('access.tictactoe',4);
  $id=$a->json('id');$this->postJson('/api/games/tictactoe',[])->assertJsonPath('id',$id)->assertJsonPath('access.tictactoe',4);
  $this->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>0,'revision'=>0])->assertOk()->assertJsonPath('state.board.0','X')->assertJsonPath('state.board.4','O')->assertJsonPath('state.revision',1);
  $this->getJson('/api/status')->assertJsonPath('rounds.tictactoe.state.board.0','X');
  $this->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>0,'revision'=>1])->assertStatus(422);
  $this->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>1,'revision'=>0])->assertStatus(422);
  $this->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>9,'revision'=>1])->assertStatus(422);
 }
 public function test_wins_draws_and_no_move_after_player_win(): void {
  foreach(T::LINES as $line){$b=array_fill(0,9,null);foreach($line as $i)$b[$i]='X';$this->assertSame(['status'=>'won','winning_line'=>$line],T::outcome($b));}
  $this->assertSame('draw',T::outcome(['X','O','X','X','O','O','O','X','X'])['status']);
  $s=T::start();$s['board']=['X','X',null,'O','O',null,null,null,null];$result=T::move($s,2,0);$this->assertSame('won',$result['status']);$this->assertNull($result['board'][5]);
 }
 public function test_computer_wins_and_blocks(): void {
  $s=T::start();$s['board']=['O','O',null,'X',null,null,null,'X',null];$this->assertSame('lost',T::move($s,8,0)['status']);
  $s=T::start();$s['board']=['X',null,null,null,'O',null,null,null,null];$result=T::move($s,1,0);$this->assertSame('O',$result['board'][2]);
 }
 public function test_quota_is_independent_and_paid_pass_includes_game(): void {
  $p=$this->guest();for($i=0;$i<5;$i++){$a=$this->postJson('/api/games/tictactoe',[])->assertOk();$r=Round::find($a->json('id'));$s=$r->state;$s['status']='draw';$r->update(['state'=>$s]);}
  $this->postJson('/api/games/tictactoe',[])->assertStatus(402);$this->postJson('/api/games/hangman',[])->assertOk();
  $p->update(['unlimited'=>true]);$this->postJson('/api/games/tictactoe',[])->assertOk();
 }
 public function test_round_belongs_to_guest_and_completed_round_cannot_move(): void {
  $this->guest();$id=$this->postJson('/api/games/tictactoe',[])->json('id');
  $r=Round::find($id);$s=$r->state;$s['status']='draw';$r->update(['state'=>$s]);$this->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>0,'revision'=>0])->assertStatus(409);
  Player::create(['token_hash'=>hash('sha256','someone-else')]);$this->withCookie('arcade_player','someone-else')->postJson('/api/rounds/'.$id,['action'=>'place','cell'=>0,'revision'=>0])->assertNotFound();
 }
}
