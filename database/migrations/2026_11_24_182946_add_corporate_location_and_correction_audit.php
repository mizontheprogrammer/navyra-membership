<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->string('company_name')->nullable());
        Schema::table('membership_applications', fn (Blueprint $table) => $table->string('company_name')->nullable());
        Schema::table('membership_transactions', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
        });
        Schema::create('membership_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->text('reason');
            $table->json('previous_state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_corrections');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
        Schema::table('membership_transactions', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('company_name'));
        Schema::table('membership_applications', fn (Blueprint $table) => $table->dropColumn('company_name'));
    }
};
