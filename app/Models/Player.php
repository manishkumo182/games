<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Player extends Model {
 use HasUuids;
 protected $guarded = [];
 protected $hidden = ['token_hash','recovery_hash','recovery_secret','shoe'];
 protected function casts(): array { return ['last_seen_at'=>'datetime','unlimited'=>'boolean','shoe'=>'array','recovery_secret'=>'encrypted']; }
 public function access(): array { return ['unlimited'=>$this->unlimited, 'fade'=>max(0,5-$this->fade_plays), 'tictactoe'=>max(0,5-$this->tictactoe_plays), 'hangman'=>max(0,5-$this->hangman_plays), 'daily'=>max(0,5-$this->daily_plays), 'blackjack'=>max(0,5-$this->blackjack_plays)]; }
}
