<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipTransaction extends Model
{
    use HasFactory;

    public const STATUSES = ['Pending', 'Paid', 'Approved', 'Expired', 'Cancelled'];

    protected $guarded = ['id'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membershipLevel(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(MembershipCorrection::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidCents(): int
    {
        return Money::cents($this->payments()->whereNull('voided_at')->sum('amount'));
    }

    public function balanceCents(): int
    {
        return max(0, Money::cents($this->amount) - $this->paidCents());
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
