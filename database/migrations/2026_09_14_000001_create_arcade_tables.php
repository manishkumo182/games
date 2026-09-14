<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('players', function(Blueprint $t) {
   $t->uuid('id')->primary(); $t->string('token_hash',64)->unique();
   $t->unsignedInteger('hangman_plays')->default(0); $t->unsignedInteger('blackjack_plays')->default(0);
   $t->boolean('unlimited')->default(false); $t->string('recovery_hash',64)->nullable()->unique();
   $t->text('recovery_secret')->nullable(); $t->json('shoe')->nullable(); $t->timestamps();
  });
  Schema::create('rounds', function(Blueprint $t) {
   $t->uuid('id')->primary(); $t->foreignUuid('player_id')->constrained()->cascadeOnDelete();
   $t->string('game'); $t->json('state'); $t->timestamps(); $t->index(['player_id','game']);
  });
  Schema::create('purchases', function(Blueprint $t) {
   $t->id(); $t->foreignUuid('player_id')->constrained(); $t->string('stripe_session')->unique();
   $t->timestamp('paid_at')->nullable(); $t->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('purchases'); Schema::dropIfExists('rounds'); Schema::dropIfExists('players'); }
};
