<?php
namespace Tests\Unit;
use App\Services\Hangman as H;
use PHPUnit\Framework\TestCase;
class HangmanTest extends TestCase {
 public function test_win_loss_and_repeated_letters(): void {
  $s=['word'=>'APPLE','difficulty'=>'easy','guesses'=>[],'limit'=>8,'status'=>'playing'];
  foreach(str_split('APLE') as $l)$s=H::guess($s,$l);$this->assertSame('won',$s['status']);$this->assertSame(str_split('APPLE'),H::view($s)['letters']);
  $s['guesses']=[];$s['status']='playing';foreach(str_split('BCDFGHIJ') as $l)$s=H::guess($s,$l);$this->assertSame('lost',$s['status']);$this->assertSame(0,H::view($s)['remaining']);
 }
 public function test_difficulty_limits(): void {foreach(['easy'=>8,'medium'=>7,'hard'=>6] as $level=>$limit){$s=H::start($level);$this->assertSame($limit,$s['limit']);$this->assertSame('playing',$s['status']);$this->assertMatchesRegularExpression('/^[A-Z]+$/',$s['word']);}}
}
