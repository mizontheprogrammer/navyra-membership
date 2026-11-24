<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory;

    protected $fillable = ['company_name', 'membership_number', 'name', 'membership_category_id', 'membership_level_id', 'email', 'phone', 'active'];

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            if (! $member->membership_number) {
                $member->membership_number = 'MEM-'.Str::ulid();
            }
        });
    }

    public function membershipCategory(): BelongsTo
    {
        return $this->belongsTo(MembershipCategory::class);
    }

    public function membershipLevel(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MembershipTransaction::class);
    }

    protected function category(): Attribute
    {
        return Attribute::get(fn () => $this->membershipCategory?->name);
    }

    protected function level(): Attribute
    {
        return Attribute::get(fn () => $this->membershipLevel?->name);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'joined_on' => 'date', 'expires_on' => 'date'];
    }
}
