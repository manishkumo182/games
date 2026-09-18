<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class FadeTacToe {
 public static function start(): array {return array_merge(TicTacToe::start(),['history'=>[],'cycles'=>0,'events'=>[]]);}
 public static function place(array $s,int $cell,string $mark): array {
  $s['board'][$cell]=$mark;$s['history'][]=['cell'=>$cell,'mark'=>$mark];
  $outcome=TicTacToe::outcome($s['board']);
  $s['events'][]=['type'=>'place','board'=>$s['board'],'removed'=>[]];
  if($outcome['status']!=='draw')return array_merge($s,$outcome);
  $removed=[];$counts=['X'=>0,'O'=>0];$kept=[];
  foreach($s['history'] as $move){if($counts[$move['mark']]<2){$removed[]=$move['cell'];$counts[$move['mark']]++;}else $kept[]=$move;}
  $s['events'][]=['type'=>'fade','board'=>$s['board'],'removed'=>$removed];
  foreach($removed as $index)$s['board'][$index]=null;
  $s['history']=$kept;$s['cycles']++;$s['status']='playing';$s['winning_line']=[];return $s;
 }
 public static function move(array $s,int $cell,int $revision): array {
  if($s['status']!=='playing'||$s['revision']!==$revision||$cell<0||$cell>8||$s['board'][$cell]!==null)throw ValidationException::withMessages(['cell'=>'That square or turn is no longer available. Try an empty square.']);
  $s['events']=[];$s=self::place($s,$cell,'X');
  if($s['status']==='playing')$s=self::place($s,TicTacToe::computer($s['board']),'O');
  $s['revision']++;return $s;
 }
}
