<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        $data = [
            'course' => 'IT0049',
            'module' => 'Laravel Foundations',
        ];

        return view('about', $data);
    }

    public function task(string $id): View
    {
        $data = [
            'id' => $id,
            'title' => 'TW33 Favorite Meal: Lunch!',
        ];

        return view('task', $data);
    }
}
