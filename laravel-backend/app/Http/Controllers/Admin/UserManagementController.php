<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $query = User::where('role', 'user')->latest();

        if ($status === 'pending') {
            $query->where('is_approved', false)->where('is_disabled', false);
        } elseif ($status === 'approved') {
            $query->where('is_approved', true)->where('is_disabled', false);
        } elseif ($status === 'disabled') {
            $query->where('is_disabled', true);
        }

        $users = $query->paginate(15)->withQueryString();

        $counts = [
            'all'      => User::where('role', 'user')->count(),
            'pending'  => User::where('role', 'user')->where('is_approved', false)->where('is_disabled', false)->count(),
            'approved' => User::where('role', 'user')->where('is_approved', true)->where('is_disabled', false)->count(),
            'disabled' => User::where('role', 'user')->where('is_disabled', true)->count(),
        ];

        return view('admin.users.index', compact('users', 'status', 'counts'));
    }

    public function approve(User $user)
    {
        $user->update([
            'is_approved'        => true,
            'approved_at'        => now(),
        ]);

        return back()->with('status', "Account for {$user->first_name} {$user->last_name} has been approved.");
    }

    public function disable(User $user)
    {
        $user->update([
            'is_disabled'  => true,
            'disabled_at'  => now(),
        ]);

        return back()->with('status', "Account for {$user->first_name} {$user->last_name} has been disabled.");
    }

    public function enable(User $user)
    {
        $user->update([
            'is_disabled' => false,
            'disabled_at' => null,
        ]);

        return back()->with('status', "Account for {$user->first_name} {$user->last_name} has been re-enabled.");
    }
}