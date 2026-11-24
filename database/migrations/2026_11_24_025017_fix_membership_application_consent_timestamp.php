<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dateTime('consented_at')->change();
        });
        DB::table('membership_applications')->whereNotNull('created_at')->update(['consented_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->timestamp('consented_at')->nullable()->change();
        });
    }
};
