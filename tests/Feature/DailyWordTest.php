<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\Player;
use App\Services\DailyWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
class DailyWordTest extends TestCase {
 use RefreshDatabase;
 private function guest(): Player { $p=Player::create(['token_hash'=>hash('sha256','daily-test')]);$this->withCredentials()->withCookie('arcade_player','daily-test');return $p; }
 public function test_library_and_direct_game_routes(): void {
  $this->get('/')->assertOk()->assertSee('/games/daily')->assertDontSee('id="hangman-view"',false);
  foreach(['hangman','blackjack','daily'] as $game)$this->get('/games/'.$game)->assertOk()->assertSee('data-game="'.$game.'"',false);
  $this->get('/games/missing')->assertNotFound();
 }
 public function test_daily_is_shared_hidden_and_resumable_even_after_completion(): void {
  $this->travelTo(now('UTC')->setDate(2026,9,14)->startOfDay());$this->guest();
  $a=$this->postJson('/api/games/daily',[])->assertOk()->assertJsonPath('access.daily',4);
  $this->assertArrayNotHasKey('answer',$a->json('state'));
  $this->postJson('/api/games/daily',[])->assertJsonPath('id',$a->json('id'))->assertJsonPath('access.daily',4);
  $this->postJson('/api/rounds/'.$a->json('id'),['action'=>'word','word'=>DailyWord::answer(DailyWord::date())])->assertJsonPath('state.status','won');
  $this->postJson('/api/games/daily',[])->assertJsonPath('id',$a->json('id'))->assertJsonPath('state.status','won')->assertJsonPath('access.daily',4);
 }
 public function test_midnight_rollover_without_jobs_expires_old_round(): void {
  $this->travelTo(now('UTC')->setDate(2026,9,14)->endOfDay());$this->guest();
  $a=$this->postJson('/api/games/daily',[])->json('id');$word=DailyWord::answer(DailyWord::date());$this->travel(2)->seconds();
  $this->assertNotSame($word,DailyWord::answer(DailyWord::date()));
  $this->getJson('/api/status')->assertJsonMissingPath('rounds.daily')->assertJsonPath('daily.date','2026-09-15');
  $this->postJson('/api/rounds/'.$a,['action'=>'word','word'=>'APPLE'])->assertStatus(409);
  $this->postJson('/api/games/daily',[])->assertJsonPath('state.date','2026-09-15')->assertJsonPath('access.daily',3);
 }
 public function test_daily_has_its_own_five_puzzle_allowance(): void {
  $this->guest();for($i=0;$i<5;$i++){$this->postJson('/api/games/daily',[])->assertOk();$this->travel(1)->days();}
  $this->postJson('/api/games/daily',[])->assertStatus(402);
  $this->postJson('/api/games/hangman',[])->assertOk();
 }
 public function test_six_guesses_invalid_words_and_duplicate_letter_scoring(): void {
  $this->assertSame(['correct','present','absent','absent','correct'],DailyWord::score('APPLE','ALGAE'));
  $this->assertSame(['present','absent','correct','absent','absent'],DailyWord::score('APPLE','PUPPY'));
  $this->guest();$id=$this->postJson('/api/games/daily',[])->json('id');
  $this->postJson('/api/rounds/'.$id,['action'=>'word','word'=>'ZZZZZ'])->assertStatus(422);
  $this->getJson('/api/status')->assertJsonCount(0,'rounds.daily.state.guesses');
  $wrong=DailyWord::answer(DailyWord::date())==='APPLE'?'BREAD':'APPLE';
  for($i=0;$i<6;$i++)$response=$this->postJson('/api/rounds/'.$id,['action'=>'word','word'=>$wrong])->assertOk();
  $response->assertJsonPath('state.status','lost')->assertJsonPath('state.answer',DailyWord::answer(DailyWord::date()));
  $this->postJson('/api/rounds/'.$id,['action'=>'word','word'=>$wrong])->assertStatus(409);
 }
}
