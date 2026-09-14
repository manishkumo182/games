<?php
namespace App\Http\Controllers;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Cookie};
use Illuminate\Support\Str;
use Stripe\{StripeClient,Webhook};
class PaymentController extends Controller {
 public function checkout(Request $r) {
  abort_unless(config('services.stripe.secret'),503,'Payments are not configured yet. Please try again later.');
  $p=$r->attributes->get('player'); abort_if($p->unlimited,409,'You already have unlimited access.');
  $stripe=new StripeClient(config('services.stripe.secret'));
  // A stable key prevents concurrent clicks creating duplicate checkout sessions.
  try {
   $session=$stripe->checkout->sessions->create([
    'mode'=>'payment','payment_method_types'=>['card'],'client_reference_id'=>$p->id,
    'metadata'=>['player_id'=>$p->id,'product'=>'arcade_unlimited'],
    'line_items'=>[['price_data'=>['currency'=>'usd','unit_amount'=>100,'product_data'=>['name'=>'Pocket Arcade — unlimited access']],'quantity'=>1]],
    'success_url'=>rtrim(config('app.url'),'/').'/payment/return?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'=>rtrim(config('app.url'),'/').'/?payment=cancelled',
   ],['idempotency_key'=>'unlock-'.$p->id.'-'.floor(time()/1800)]);
   DB::table('purchases')->insertOrIgnore(['player_id'=>$p->id,'stripe_session'=>$session->id,'created_at'=>now(),'updated_at'=>now()]);
   return ['url'=>$session->url];
  } catch(\Stripe\Exception\ApiErrorException $e) { report($e); abort(502,'Checkout could not be opened. Please try again.'); }
 }
 public function fulfill($s): void {
  if($s->payment_status!=='paid' || $s->mode!=='payment' || $s->currency!=='usd' || $s->amount_total!==100 || ($s->metadata->product ?? '')!=='arcade_unlimited') return;
  $id=$s->metadata->player_id ?? null;
  if(!$id || $s->client_reference_id!==$id) return;
  DB::transaction(function() use($s,$id) {
   $p=Player::lockForUpdate()->find($id); if(!$p) return;
   if(!$p->recovery_hash) { $code=Str::random(40); $p->recovery_hash=hash('sha256',$code); $p->recovery_secret=$code; }
   $p->unlimited=true; $p->save();
   DB::table('purchases')->updateOrInsert(['stripe_session'=>$s->id],['player_id'=>$id,'paid_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  },3);
 }
 public function webhook(Request $r) {
  abort_unless(config('services.stripe.webhook_secret'),503);
  try { $event=Webhook::constructEvent($r->getContent(),$r->header('Stripe-Signature',''),config('services.stripe.webhook_secret')); }
  catch(\UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $e) { return response('Invalid signature',400); }
  if(in_array($event->type,['checkout.session.completed','checkout.session.async_payment_succeeded'])) $this->fulfill($event->data->object);
  return response('ok');
 }
 public function returned(Request $r) {
  $r->validate(['session_id'=>'required|string|max:255']);
  abort_unless(config('services.stripe.secret'),503);
  try { $s=(new StripeClient(config('services.stripe.secret')))->checkout->sessions->retrieve($r->session_id); }
  catch(\Stripe\Exception\ApiErrorException $e) { return redirect('/?payment=pending'); }
  abort_unless(($s->metadata->player_id ?? null)===$r->attributes->get('player')->id,403);
  $this->fulfill($s);
  return redirect('/?payment='.($s->payment_status==='paid' ? 'success' : 'pending'));
 }
 public function restore(Request $r) {
  $data=$r->validate(['code'=>'required|string|size:40']);
  $p=Player::where('recovery_hash',hash('sha256',$data['code']))->where('unlimited',true)->first();
  abort_unless($p,422,'That recovery code was not found. Check the code and try again.');
  // Keep other paid devices working by copying the entitlement to this guest identity.
  $current=$r->attributes->get('player'); $current->unlimited=true;
  $current->recovery_secret=$p->recovery_secret; $current->save();
  return ['access'=>$current->access(),'recovery_code'=>$p->recovery_secret];
 }
}
