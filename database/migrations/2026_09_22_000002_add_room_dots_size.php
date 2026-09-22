<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('game_rooms',fn(Blueprint $t)=>$t->unsignedTinyInteger('dots_size')->default(3));}
 public function down(): void {Schema::table('game_rooms',fn(Blueprint $t)=>$t->dropColumn('dots_size'));}
};
