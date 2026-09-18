<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class TicTacToe {
 public const LINES=[[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
 public static function start(): array { return ['board'=>array_fill(0,9,null),'status'=>'playing','winning_line'=>[],'revision'=>0]; }
 public static function outcome(array $board): array {
  foreach(self::LINES as $line)if($board[$line[0]]!==null&&$board[$line[0]]===$board[$line[1]]&&$board[$line[1]]===$board[$line[2]])return ['status'=>$board[$line[0]]==='X'?'won':'lost','winning_line'=>$line];
  return ['status'=>in_array(null,$board,true)?'playing':'draw','winning_line'=>[]];
 }
 public static function computer(array $board): int {
  $empty=array_keys($board,null,true);
  // Take a win, block a win, then prefer the center and corners.
  foreach(['O','X'] as $mark)foreach($empty as $cell){$test=$board;$test[$cell]=$mark;if(self::outcome($test)['status']===($mark==='O'?'lost':'won'))return $cell;}
  if($board[4]===null)return 4;
  $corners=array_values(array_intersect($empty,[0,2,6,8]));$choices=$corners?:$empty;
  return $choices[random_int(0,count($choices)-1)];
 }
 public static function move(array $s,int $cell,int $revision): array {
  if($s['status']!=='playing'||$s['revision']!==$revision||$cell<0||$cell>8||$s['board'][$cell]!==null)throw ValidationException::withMessages(['cell'=>'That square or turn is no longer available. Please try an empty square.']);
  $s['board'][$cell]='X';$s=array_merge($s,self::outcome($s['board']));
  if($s['status']==='playing'){$s['board'][self::computer($s['board'])]='O';$s=array_merge($s,self::outcome($s['board']));}
  $s['revision']++;return $s;
 }
}
