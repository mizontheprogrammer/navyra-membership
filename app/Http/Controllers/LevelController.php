<?php

namespace App\Http\Controllers;

use App\Models\MembershipLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LevelController extends Controller
{
    public function index(): View
    {
        return view('levels', ['records' => MembershipLevel::withCount('members')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('catalog-form', ['record' => new MembershipLevel, 'kind' => 'levels', 'label' => 'Level']);
    }

    public function store(Request $request): RedirectResponse
    {
        MembershipLevel::create($this->details($request));

        return redirect()->route('levels.index')->with('success', 'Level added.');
    }

    public function edit(MembershipLevel $level): View
    {
        return view('catalog-form', ['record' => $level, 'kind' => 'levels', 'label' => 'Level']);
    }

    public function update(Request $request, MembershipLevel $level): RedirectResponse
    {
        $level->update($this->details($request, $level));

        return redirect()->route('levels.index')->with('success', 'Level updated.');
    }

    public function destroy(MembershipLevel $level): RedirectResponse
    {
        $record = $level;
        if ($record->members()->exists() || $record->transactions()->exists()) {
            return back()->withErrors(['catalog' => 'This level is assigned to members or transactions and cannot be deleted.']);
        }
        $record->delete();

        return redirect()->route('levels.index')->with('success', 'Level deleted.');
    }

    private function details(Request $request, ?MembershipLevel $record = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('membership_levels')->ignore($record?->id)], 'fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'], 'validity_days' => ['nullable', 'integer', 'min:1', 'max:36500'], 'benefits' => ['nullable', 'string', 'max:5000']]);
    }
}
