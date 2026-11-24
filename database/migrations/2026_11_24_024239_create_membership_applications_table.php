<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_category_id')->constrained()->restrictOnDelete();
            $table->string('reference_no')->unique();
            $table->uuid('submission_key')->unique();
            $table->string('full_name', 150);
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('category_name');
            $table->string('category_level')->nullable();
            $table->decimal('category_fee', 10, 2)->default(0);
            $table->timestamp('consented_at');
            $table->timestamp('verification_queued_at')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('membership_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
