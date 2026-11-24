<?php

namespace App\Http\Controllers;

use App\Models\MembershipCategory;
use App\Models\MembershipTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories', ['records' => MembershipCategory::withCount('members')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('catalog-form', ['record' => new MembershipCategory, 'kind' => 'categories', 'label' => 'Category']);
    }

    public function store(Request $request): RedirectResponse
    {
        MembershipCategory::create($this->details($request));

        return redirect()->route('categories.index')->with('success', 'Category added.');
    }

    public function edit(MembershipCategory $category): View
    {
        return view('catalog-form', ['record' => $category, 'kind' => 'categories', 'label' => 'Category']);
    }

    public function update(Request $request, MembershipCategory $category): RedirectResponse
    {
        $category->update($this->details($request, $category));

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(MembershipCategory $category): RedirectResponse
    {
        $record = $category;
        if ($record->members()->exists() || $record->applications()->exists() || MembershipTransaction::where('membership_category_id', $record->id)->orWhere(fn ($query) => $query->whereNull('membership_category_id')->where('category_name', $record->name))->exists()) {
            return back()->withErrors(['catalog' => 'This category is assigned to members or transactions and cannot be deleted.']);
        }
        $record->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }

    private function details(Request $request, ?MembershipCategory $record = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('membership_categories')->ignore($record?->id)], 'description' => ['nullable', 'string', 'max:5000'], 'level' => ['nullable', 'string', 'max:100'], 'fee' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'], 'is_active' => ['sometimes', 'required', 'boolean']]);
    }
}
