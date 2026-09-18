<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class GameInvite extends Model {
 use HasUuids;
 protected $table='game_invites';
 protected $guarded=[];
 protected function casts(): array {return ['expires_at'=>'datetime'];}
}
