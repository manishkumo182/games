<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('players',function(Blueprint $t){$t->unsignedInteger('daily_plays')->default(0);});
  Schema::table('rounds',function(Blueprint $t){$t->date('puzzle_date')->nullable();$t->unique(['player_id','game','puzzle_date']);});
 }
 public function down(): void {
  Schema::table('rounds',function(Blueprint $t){$t->dropUnique(['player_id','game','puzzle_date']);$t->dropColumn('puzzle_date');});
  Schema::table('players',function(Blueprint $t){$t->dropColumn('daily_plays');});
 }
};
