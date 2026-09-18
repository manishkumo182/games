<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('players',fn(Blueprint $t)=>$t->unsignedInteger('fade_plays')->default(0));}
 public function down(): void {Schema::table('players',fn(Blueprint $t)=>$t->dropColumn('fade_plays'));}
};
