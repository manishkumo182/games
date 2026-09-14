<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Round extends Model {
 use HasUuids;
 protected $guarded=[];
 protected function casts(): array { return ['state'=>'array']; }
}
