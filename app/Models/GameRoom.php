<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class GameRoom extends Model {
 use HasUuids;
 protected $table='game_rooms';
 protected $guarded=[];
 protected function casts(): array {return ['state'=>'array','version'=>'integer','dots_size'=>'integer'];}
}
