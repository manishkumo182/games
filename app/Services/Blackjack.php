<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class Blackjack {
 public static function shoe(): array {
  $cards=[];
  for($d=0;$d<6;$d++) foreach(['S','H','D','C'] as $s) foreach(['A','2','3','4','5','6','7','8','9','10','J','Q','K'] as $r) $cards[]=['rank'=>$r,'suit'=>$s,'id'=>"$d-$s-$r"];
  // Unbiased Fisher–Yates. Cards are removed from the shoe when dealt.
  for($i=count($cards)-1;$i>0;$i--) { $j=random_int(0,$i); [$cards[$i],$cards[$j]]=[$cards[$j],$cards[$i]]; }
  return $cards;
 }
 public static function value(array $c): int { return $c['rank']==='A' ? 11 : (is_numeric($c['rank']) ? (int)$c['rank'] : 10); }
 public static function total(array $cards): int {
  $sum=array_sum(array_map(self::value(...),$cards)); $aces=count(array_filter($cards,fn($c)=>$c['rank']==='A'));
  while($sum>21 && $aces-->0) $sum-=10;
  return $sum;
 }
 private static function hand(array $cards,bool $split=false): array { return ['cards'=>$cards,'bet'=>10,'split'=>$split,'done'=>false]; }
 public static function start(array &$shoe): array {
  if(count($shoe)<78) $shoe=self::shoe();
  $p=[array_pop($shoe)]; $d=[array_pop($shoe)]; $p[]=array_pop($shoe); $d[]=array_pop($shoe);
  $s=['hands'=>[self::hand($p)],'dealer'=>$d,'active'=>0,'status'=>'playing'];
  if(self::total($p)===21 || self::total($d)===21) $s=self::settle($s);
  return $s;
 }
 public static function actions(array $s): array {
  if($s['status']!=='playing') return [];
  $h=$s['hands'][$s['active']]; $actions=['hit','stand'];
  if(count($h['cards'])===2) $actions[]='double';
  if(count($h['cards'])===2 && count($s['hands'])<4 && self::value($h['cards'][0])===self::value($h['cards'][1])) $actions[]='split';
  return $actions;
 }
 public static function act(array $s,string $action,array &$shoe): array {
  if(!in_array($action,self::actions($s))) throw ValidationException::withMessages(['action'=>'That move is not available.']);
  $i=$s['active']; $h=&$s['hands'][$i];
  if($action==='hit' || $action==='double') {
   $h['cards'][]=array_pop($shoe);
   if($action==='double') { $h['bet']*=2; $h['done']=true; }
   if(self::total($h['cards'])>=21) $h['done']=true;
  } elseif($action==='stand') $h['done']=true;
  else {
   $cards=$h['cards']; $a=self::hand([$cards[0],array_pop($shoe)],true); $b=self::hand([$cards[1],array_pop($shoe)],true);
   $aces=$cards[0]['rank']==='A';
   $a['done']=$aces || self::total($a['cards'])===21; $b['done']=$aces || self::total($b['cards'])===21;
   unset($h); array_splice($s['hands'],$i,1,[$a,$b]);
  }
  $next=null; foreach($s['hands'] as $idx=>$hand) if(!$hand['done']) { $next=$idx; break; }
  if($next!==null) { $s['active']=$next; return $s; }
  $live=array_filter($s['hands'],fn($hand)=>self::total($hand['cards'])<=21);
  if($live) while(self::total($s['dealer'])<17) $s['dealer'][]=array_pop($shoe);
  return self::settle($s);
 }
 public static function settle(array $s): array {
  $dealer=self::total($s['dealer']); $dealerNatural=$dealer===21 && count($s['dealer'])===2;
  foreach($s['hands'] as &$h) {
   $t=self::total($h['cards']); $natural=$t===21 && count($h['cards'])===2 && !$h['split'];
   if($t>21) $result='bust';
   elseif($dealerNatural) $result=$natural ? 'push' : 'lose';
   elseif($natural) $result='blackjack';
   elseif($dealer>21 || $t>$dealer) $result='win';
   elseif($t===$dealer) $result='push';
   else $result='lose';
   $h['result']=$result; $h['net']=match($result) {'blackjack'=>$h['bet']*1.5,'win'=>$h['bet'],'push'=>0,default=>-$h['bet']}; $h['done']=true;
  }
  unset($h); $s['status']='finished'; $s['net']=array_sum(array_column($s['hands'],'net')); return $s;
 }
 public static function view(array $s): array {
  $s['actions']=self::actions($s);
  foreach($s['hands'] as &$h) $h['total']=self::total($h['cards']); unset($h);
  if($s['status']==='playing') { $s['dealer']=[$s['dealer'][0],null]; $s['dealer_total']=self::total([$s['dealer'][0]]); }
  else $s['dealer_total']=self::total($s['dealer']);
  return $s;
 }
}
