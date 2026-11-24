<?php

namespace App\Http\Requests;

use App\Models\MembershipCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_category_id' => ['required', 'integer', Rule::exists('membership_categories', 'id')->where('is_active', true)],
            'company_name' => [Rule::requiredIf(fn () => MembershipCategory::find($this->input('membership_category_id'))?->name === 'Corporate'), 'nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'], 'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'consent' => ['required', 'accepted'], 'submission_key' => ['required', 'uuid'], 'website' => ['prohibited'],
        ];
    }
}
