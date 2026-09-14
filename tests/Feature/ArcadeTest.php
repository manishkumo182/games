<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Player,Round};
use App\Http\Controllers\PaymentController;
use Stripe\StripeObject;
class ArcadeTest extends TestCase {
 use RefreshDatabase;
 private function guest(): Player {$token='guest-test-token';$p=Player::create(['token_hash'=>hash('sha256',$token)]);$this->withCredentials()->withCookie('arcade_player',$token);return $p;}
 public function test_five_free_plays_per_game_and_no_login(): void {
  $p=$this->guest();
  for($i=0;$i<5;$i++){ $response=$this->postJson('/api/games/hangman',['difficulty'=>'hard'])->assertOk()->assertJsonPath('access.hangman',4-$i);$round=Round::find($response->json('id'));$s=$round->state;$s['status']='lost';$round->state=$s;$round->save(); }
  $this->postJson('/api/games/hangman',[])->assertStatus(402);
  $this->postJson('/api/games/blackjack',[])->assertOk()->assertJsonPath('access.blackjack',4);
 }
 public function test_unfinished_round_resumes_and_secret_is_hidden(): void {
  $this->guest();$a=$this->postJson('/api/games/hangman',['difficulty'=>'medium'])->assertOk();$this->assertArrayNotHasKey('word',$a->json('state'));
  $b=$this->postJson('/api/games/hangman',[])->assertOk();$this->assertSame($a->json('id'),$b->json('id'));$this->assertSame(4,$b->json('access.hangman'));
  $this->getJson('/api/status')->assertJsonPath('rounds.hangman.id',$a->json('id'));
 }
 public function test_guess_validation_duplicate_letters_and_game_ownership(): void {
  $p=$this->guest();$res=$this->postJson('/api/games/hangman',[]);$id=$res->json('id');
  $this->postJson('/api/rounds/'.$id,['action'=>'guess','letter'=>'AA'])->assertUnprocessable();
  $a=$this->postJson('/api/rounds/'.$id,['action'=>'guess','letter'=>'a'])->assertOk();$b=$this->postJson('/api/rounds/'.$id,['action'=>'guess','letter'=>'a'])->assertOk();$this->assertSame($a->json('state'),$b->json('state'));
  $other=Player::create(['token_hash'=>hash('sha256','other')]);$this->withCookie('arcade_player','other')->postJson('/api/rounds/'.$id,['action'=>'guess','letter'=>'b'])->assertNotFound();
 }
 public function test_paid_access_has_no_play_limit_and_can_be_restored(): void {
  $p=$this->guest();$p->update(['unlimited'=>true,'hangman_plays'=>100,'recovery_hash'=>hash('sha256',str_repeat('a',40)),'recovery_secret'=>str_repeat('a',40)]);
  $this->postJson('/api/games/hangman',[])->assertOk();
  $other=Player::create(['token_hash'=>hash('sha256','other')]);$this->withCookie('arcade_player','other')->postJson('/api/restore',['code'=>str_repeat('a',40)])->assertOk()->assertJsonPath('access.unlimited',true);
  $this->assertTrue($other->fresh()->unlimited);
 }
 private function stripeSession(Player $p,array $extra=[]): StripeObject {return StripeObject::constructFrom(array_replace(['id'=>'cs_test_paid','payment_status'=>'paid','mode'=>'payment','currency'=>'usd','amount_total'=>100,'client_reference_id'=>$p->id,'metadata'=>['player_id'=>$p->id,'product'=>'arcade_unlimited']],$extra));}
 public function test_payment_fulfillment_checks_amount_and_is_idempotent(): void {
  $p=$this->guest();$controller=new PaymentController;
  foreach([['amount_total'=>1],['currency'=>'eur'],['payment_status'=>'unpaid'],['mode'=>'subscription'],['client_reference_id'=>'wrong']] as $bad){$controller->fulfill($this->stripeSession($p,$bad));$this->assertFalse($p->fresh()->unlimited);}
  $controller->fulfill($this->stripeSession($p));$code=$p->fresh()->recovery_secret;$controller->fulfill($this->stripeSession($p));$this->assertTrue($p->fresh()->unlimited);$this->assertSame($code,$p->fresh()->recovery_secret);$this->assertDatabaseCount('purchases',1);
 }
 public function test_signed_webhook_unlocks_and_forged_event_is_rejected(): void {
  $p=$this->guest();config(['services.stripe.webhook_secret'=>'whsec_test']);
  $payload=json_encode(['id'=>'evt_test','object'=>'event','type'=>'checkout.session.completed','data'=>['object'=>$this->stripeSession($p)->toArray()]]);$time=time();$signature=hash_hmac('sha256',$time.'.'.$payload,'whsec_test');
  $this->call('POST','/stripe/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>"t=$time,v1=bad"],$payload)->assertStatus(400);$this->assertFalse($p->fresh()->unlimited);
  $this->call('POST','/stripe/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>"t=$time,v1=$signature"],$payload)->assertOk();$this->assertTrue($p->fresh()->unlimited);
 }
 public function test_missing_checkout_config_does_not_fake_payment(): void {$p=$this->guest();config(['services.stripe.secret'=>null]);$this->postJson('/api/checkout')->assertStatus(503);$this->assertFalse($p->fresh()->unlimited);}
}
