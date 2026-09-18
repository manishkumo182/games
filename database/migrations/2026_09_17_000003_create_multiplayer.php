<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('players',function(Blueprint $t){$t->string('handle',90)->nullable()->unique();$t->timestamp('last_seen_at')->nullable()->index();});
  Schema::create('game_rooms',function(Blueprint $t){$t->uuid('id')->primary();$t->string('code',32)->unique();$t->string('game');$t->foreignUuid('host_id')->constrained('players');$t->string('status')->default('lobby');$t->unsignedInteger('version')->default(0);$t->json('state')->nullable();$t->timestamps();});
  Schema::create('room_seats',function(Blueprint $t){$t->id();$t->foreignUuid('room_id')->constrained('game_rooms')->cascadeOnDelete();$t->foreignUuid('player_id')->unique()->constrained('players')->cascadeOnDelete();$t->boolean('ready')->default(false);$t->timestamp('seen_at');$t->timestamps();});
  Schema::create('game_invites',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('room_id')->constrained('game_rooms')->cascadeOnDelete();$t->foreignUuid('sender_id')->constrained('players');$t->foreignUuid('recipient_id')->constrained('players');$t->string('status')->default('pending');$t->timestamp('expires_at');$t->timestamps();$t->unique(['room_id','recipient_id']);});
 }
 public function down(): void {Schema::dropIfExists('game_invites');Schema::dropIfExists('room_seats');Schema::dropIfExists('game_rooms');Schema::table('players',function(Blueprint $t){$t->dropColumn(['handle','last_seen_at']);});}
};
