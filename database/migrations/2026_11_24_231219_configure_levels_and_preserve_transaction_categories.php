<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_transactions', function (Blueprint $table) {
            $table->foreignId('membership_category_id')->nullable()->constrained()->restrictOnDelete();
        });
        foreach (DB::table('membership_categories')->pluck('id', 'name') as $name => $id) {
            DB::table('membership_transactions')->where('category_name', $name)->update(['membership_category_id' => $id]);
        }
        $benefits = 'Membership record and access to regular member activities.';
        foreach (['Basic' => 200, 'Silver' => 400, 'Gold' => 600, 'Platinum' => 1000] as $name => $fee) {
            if ($name === 'Silver') {
                $benefits .= ' Priority registration for member activities.';
            }
            if ($name === 'Gold') {
                $benefits .= ' Access to special member events.';
            }
            if ($name === 'Platinum') {
                $benefits .= ' Personalized membership assistance and priority event reservations.';
            }
            DB::table('membership_levels')->where('name', $name)->update(['fee' => $fee, 'validity_days' => 365, 'benefits' => $benefits, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('membership_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('membership_category_id'));
        // Configured catalogue values are intentionally retained; old transaction snapshots are never changed.
    }
};
