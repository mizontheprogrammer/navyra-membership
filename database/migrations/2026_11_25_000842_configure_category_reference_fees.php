<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['Student' => 200, 'Regular' => 400, 'Senior Citizen' => 200, 'Corporate' => 1000] as $name => $fee) {
            DB::table('membership_categories')->where('name', $name)->where('fee', 0)->update(['fee' => $fee, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Retain configured fees so a rollback does not discard administrator changes.
    }
};
