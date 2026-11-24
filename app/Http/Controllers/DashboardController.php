<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'totalMembers' => Member::count(), 'activeMembers' => Member::where('active', true)->count(),
            'pendingTransactions' => MembershipTransaction::where('status', 'Pending')->count(),
            'demoMembers' => Member::whereLike('membership_number', 'DEMO-MEM-%')->count(),
            'collectedPayments' => Payment::whereNull('voided_at')->sum('amount'), 'recentTransactions' => MembershipTransaction::latest()->limit(5)->get(),
        ]);
    }
}
