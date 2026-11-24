<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('setup_complete')->default(false);
        });
        DB::table('system_settings')->insert(['id' => 1, 'setup_complete' => false]);
        Schema::table('members', function (Blueprint $table) {
            $table->string('membership_number')->nullable()->unique();
            $table->foreignId('membership_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('membership_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('joined_on')->nullable();
            $table->date('expires_on')->nullable();
        });
        foreach (DB::table('members')->orderBy('id')->get() as $member) {
            $categoryId = DB::table('membership_categories')->where('name', $member->category)->value('id');
            if (! $categoryId) {
                $categoryId = DB::table('membership_categories')->insertGetId(['name' => $member->category, 'created_at' => now(), 'updated_at' => now()]);
            }
            $levelId = DB::table('membership_levels')->where('name', $member->level)->value('id');
            if (! $levelId) {
                $levelId = DB::table('membership_levels')->insertGetId(['name' => $member->level, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('members')->where('id', $member->id)->update(['membership_number' => 'MEM-'.str_pad((string) $member->id, 8, '0', STR_PAD_LEFT), 'membership_category_id' => $categoryId, 'membership_level_id' => $levelId]);
        }
        Schema::table('members', function (Blueprint $table) {
            $table->string('membership_number')->nullable(false)->change();
            $table->unsignedBigInteger('membership_category_id')->nullable(false)->change();
            $table->unsignedBigInteger('membership_level_id')->nullable(false)->change();
            $table->dropColumn(['category', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('category')->default('Regular');
            $table->string('level')->default('Basic');
        });
        foreach (DB::table('members')->get() as $member) {
            DB::table('members')->where('id', $member->id)->update(['category' => DB::table('membership_categories')->where('id', $member->membership_category_id)->value('name'), 'level' => DB::table('membership_levels')->where('id', $member->membership_level_id)->value('name')]);
        }
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_category_id');
            $table->dropConstrainedForeignId('membership_level_id');
            $table->dropColumn(['membership_number', 'joined_on', 'expires_on']);
        });
        Schema::dropIfExists('system_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
