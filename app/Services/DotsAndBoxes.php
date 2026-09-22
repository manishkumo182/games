<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class DotsAndBoxes {
 public static function start(int $size=3): array {if($size<3||$size>8)throw ValidationException::withMessages(['size'=>'Choose a board from 3×3 to 8×8.']);return ['size'=>$size,'edges'=>array_fill(0,2*$size*($size+1),null),'boxes'=>array_fill(0,$size*$size,null),'scores'=>['X'=>0,'O'=>0],'next'=>'X','status'=>'playing','revision'=>0];}
 public static function sides(int $box,int $size=3): array {$r=intdiv($box,$size);$c=$box%$size;$v=$size*($size+1)+$r*($size+1)+$c;return [$r*$size+$c,($r+1)*$size+$c,$v,$v+1];}
 public static function place(array $s,int $edge,string $mark): array {
  if($s['status']!=='playing'||$s['next']!==$mark||$edge<0||$edge>=count($s['edges'])||$s['edges'][$edge]!==null)throw ValidationException::withMessages(['edge'=>'Choose an available line on your turn.']);
  $s['edges'][$edge]=$mark;$claimed=0;
  for($b=0;$b<count($s['boxes']);$b++)if($s['boxes'][$b]===null&&!in_array(null,array_map(fn($e)=>$s['edges'][$e],self::sides($b,$s['size']??3)),true)){$s['boxes'][$b]=$mark;$s['scores'][$mark]++;$claimed++;}
  $s['last_claimed']=$claimed;$s['next']=$claimed?$mark:($mark==='X'?'O':'X');
  if(!in_array(null,$s['boxes'],true))$s['status']=$s['scores']['X']===$s['scores']['O']?'draw':($s['scores']['X']>$s['scores']['O']?'won':'lost');
  return $s;
 }
 public static function computer(array $s): int {
  $safe=[];$empty=array_keys($s['edges'],null,true);
  foreach($empty as $edge){$risk=false;foreach(range(0,count($s['boxes'])-1) as $b){$sides=self::sides($b,$s['size']??3);if(!in_array($edge,$sides,true)||$s['boxes'][$b]!==null)continue;$count=count(array_filter($sides,fn($e)=>$s['edges'][$e]!==null));if($count===3)return $edge;if($count===2)$risk=true;}if(!$risk)$safe[]=$edge;}
  $choices=$safe?:$empty;return $choices[random_int(0,count($choices)-1)];
 }
 public static function move(array $s,int $edge,int $revision): array {
  if($revision!==$s['revision'])throw ValidationException::withMessages(['revision'=>'The board changed. Try again.']);
  $s=self::place($s,$edge,'X');while($s['status']==='playing'&&$s['next']==='O')$s=self::place($s,self::computer($s),'O');$s['revision']++;return $s;
 }
}
