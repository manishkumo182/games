<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RoomSeat extends Model {
 protected $table='room_seats';
 protected $guarded=[];
 protected function casts(): array {return ['ready'=>'boolean','seen_at'=>'datetime'];}
}
