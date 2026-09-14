<?php
namespace Tests\Unit;
use App\Services\Blackjack as B;
use PHPUnit\Framework\TestCase;
class BlackjackTest extends TestCase {
 private function cards(string ...$r): array {return array_map(fn($v)=>['rank'=>$v,'suit'=>'S','id'=>uniqid()],$r);}
 private function state(array $p,array $d,bool $split=false): array {return ['hands'=>[['cards'=>$p,'bet'=>10,'split'=>$split,'done'=>false]],'dealer'=>$d,'active'=>0,'status'=>'playing'];}
 public function test_shoe_has_six_complete_decks_and_random_order(): void {
  $s=B::shoe();$this->assertCount(312,$s);$this->assertCount(312,array_unique(array_column($s,'id')));
  $counts=array_count_values(array_map(fn($c)=>$c['rank'].$c['suit'],$s));$this->assertCount(52,$counts);$this->assertSame([6],array_values(array_unique($counts)));$this->assertNotSame($s,B::shoe());
 }
 public function test_aces_adjust_without_false_busts(): void {$this->assertSame(12,B::total($this->cards('A','A')));$this->assertSame(21,B::total($this->cards('A','A','9')));$this->assertSame(13,B::total($this->cards('A','A','K','A')));}
 public function test_naturals_push_and_split_twenty_one(): void {
  $s=B::settle($this->state($this->cards('A','K'),$this->cards('10','9')));$this->assertSame(15.0,$s['net']);
  $s=B::settle($this->state($this->cards('A','K'),$this->cards('A','Q')));$this->assertSame(0,$s['net']);
  $s=B::settle($this->state($this->cards('A','K'),$this->cards('10','9'),true));$this->assertSame(10,$s['net']);
  $s=B::settle($this->state($this->cards('7','7','7'),$this->cards('A','K')));$this->assertSame(-10,$s['net']);
 }
 public function test_dealer_stands_on_soft_seventeen(): void {$shoe=$this->cards('K');$s=B::act($this->state($this->cards('10','8'),$this->cards('A','6')),'stand',$shoe);$this->assertCount(2,$s['dealer']);$this->assertSame(10,$s['net']);$this->assertCount(1,$shoe);}
 public function test_double_draws_once_and_doubles_stake(): void {$shoe=$this->cards('K');$s=B::act($this->state($this->cards('5','6'),$this->cards('10','8')),'double',$shoe);$this->assertCount(3,$s['hands'][0]['cards']);$this->assertSame(20,$s['net']);$this->assertSame('finished',$s['status']);}
 public function test_split_aces_get_one_card_each(): void {$shoe=$this->cards('9','K');$s=B::act($this->state($this->cards('A','A'),$this->cards('10','8')),'split',$shoe);$this->assertCount(2,$s['hands']);$this->assertSame('finished',$s['status']);$this->assertSame(20,$s['net']);$this->assertSame([],B::actions($s));}
 public function test_split_advances_between_hands(): void {$shoe=$this->cards('K','3','2');$s=B::act($this->state($this->cards('8','8'),$this->cards('10','8')),'split',$shoe);$this->assertSame(0,$s['active']);$s=B::act($s,'stand',$shoe);$this->assertSame(1,$s['active']);$s=B::act($s,'double',$shoe);$this->assertSame('finished',$s['status']);$this->assertSame(10,$s['net']);}
 public function test_hole_card_and_shoe_are_not_in_public_state(): void {$s=B::view($this->state($this->cards('10','8'),$this->cards('A','6')));$this->assertNull($s['dealer'][1]);$this->assertArrayNotHasKey('shoe',$s);}
 public function test_dealing_consumes_cards_in_alternating_order(): void {$shoe=B::shoe();$copy=$shoe;$s=B::start($shoe);$this->assertCount(308,$shoe);$this->assertSame($copy[311],$s['hands'][0]['cards'][0]);$this->assertSame($copy[310],$s['dealer'][0]);$this->assertSame($copy[309],$s['hands'][0]['cards'][1]);}
 public function test_thousands_of_rounds_never_duplicate_or_lose_cards(): void {
  $shoe=[];for($i=0;$i<1500;$i++){
   $before=count($shoe)<78?312:count($shoe);$s=B::start($shoe);
   while($s['status']==='playing'){$actions=B::actions($s);$a=in_array('split',$actions)&&$i%7===0?'split':(in_array('double',$actions)&&$i%5===0?'double':(B::total($s['hands'][$s['active']]['cards'])<17?'hit':'stand'));$s=B::act($s,$a,$shoe);}
   $dealt=$s['dealer'];foreach($s['hands'] as $h)$dealt=array_merge($dealt,$h['cards']);
   $this->assertSame($before,count($shoe)+count($dealt));$all=array_merge($shoe,$dealt);$this->assertCount(count($all),array_unique(array_column($all,'id')));
  }
 }
}
