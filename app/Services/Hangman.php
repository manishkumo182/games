<?php
namespace App\Services;
class Hangman {
 private const WORDS = [
  'easy'=>['APPLE','BEACH','BREAD','CHAIR','CLOUD','DANCE','DREAM','EARTH','FLAME','FRUIT','GRAPE','GREEN','HEART','HORSE','HOUSE','JUICE','LEMON','LIGHT','MAGIC','MOUSE','MUSIC','NIGHT','OCEAN','PANDA','PAPER','PEACH','PIANO','PIZZA','PLANT','QUEEN','RIVER','ROBOT','SHEEP','SHOES','SMILE','SPACE','SPOON','STARS','STONE','STORM','SUGAR','TABLE','TIGER','TOAST','TRAIN','WATER','WHALE','WHEAT','WORLD','ZEBRA'],
  'medium'=>['BALLOON','BLANKET','CABINET','CAPTAIN','CARAVAN','CRYSTAL','DOLPHIN','FEATHER','FIREFLY','GARDEN','GLACIER','HARMONY','JOURNEY','JUNGLE','KITCHEN','LANTERN','LIBRARY','MEADOW','MYSTERY','ORCHARD','PAINTER','PENGUIN','PLANET','POPCORN','RAINBOW','SHELTER','SILENCE','SPARROW','SUNRISE','THUNDER','TREASURE','TRUMPET','TURTLE','VILLAGE','VOLCANO','WHISPER','WINDOW','WINTER'],
  'hard'=>['AMBIGUOUS','ASTRONAUT','AVALANCHE','BENEVOLENT','BIOGRAPHY','BUTTERFLY','CATHEDRAL','CHAMELEON','CHRONICLE','CINNAMON','CONUNDRUM','CROCODILE','DICHOTOMY','ECOSYSTEM','ENIGMATIC','EXQUISITE','FREQUENCY','HIERARCHY','HYPOTHESIS','JUXTAPOSE','KALEIDOSCOPE','LABYRINTH','LUMINOUS','METAMORPHOSIS','NOSTALGIA','OBSIDIAN','PARADOXICAL','PHENOMENON','QUARTZITE','QUIZZICAL','RHAPSODY','SILHOUETTE','SYMMETRY','SYNCHRONIZE','UNANIMOUS','XYLOPHONE']
 ];
 public static function start(string $difficulty): array {
  $words=self::WORDS[$difficulty];
  return ['word'=>$words[random_int(0,count($words)-1)],'difficulty'=>$difficulty,'guesses'=>[],'limit'=>['easy'=>8,'medium'=>7,'hard'=>6][$difficulty],'status'=>'playing'];
 }
 public static function guess(array $s,string $letter): array {
  if (!in_array($letter,$s['guesses'])) $s['guesses'][]=$letter;
  $wrong=count(array_diff($s['guesses'],str_split($s['word'])));
  if (!array_diff(str_split($s['word']),$s['guesses'])) $s['status']='won';
  elseif ($wrong >= $s['limit']) $s['status']='lost';
  return $s;
 }
 public static function view(array $s): array {
  $s['letters']=array_map(fn($c)=>in_array($c,$s['guesses']) || $s['status']!=='playing' ? $c : '',str_split($s['word']));
  $s['wrong']=array_values(array_diff($s['guesses'],str_split($s['word'])));
  $s['remaining']=$s['limit']-count($s['wrong']);
  unset($s['word']); return $s;
 }
}
