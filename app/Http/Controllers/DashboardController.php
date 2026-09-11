<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load('organization');

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => $user->only('id', 'name', 'email', 'role'),
                'organization' => $user->organization?->only('id', 'name', 'type', 'code'),
            ],
        ]);
    }
}
