<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminSessionController extends Controller
{
    private const FIXED_ADMIN_LOGIN = 'admin';

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $identifier = trim((string) $validated['login']);
        $password = (string) $validated['password'];

        $user = $this->findAdminLoginUser($identifier);

        if (! $user || ! Hash::check($password, $user->password)) {
            return back()
                ->withErrors(['login' => 'Invalid credentials.'])
                ->onlyInput('login');
        }

        if ($user->isBlockedFromLogin()) {
            return back()
                ->withErrors(['login' => 'Account suspended.'])
                ->onlyInput('login');
        }

        if ($user->email !== null && $user->email_verified_at === null) {
            return back()
                ->withErrors(['login' => 'Email not verified yet.'])
                ->onlyInput('login');
        }

        if (! $this->isAdminUser($user)) {
            return back()
                ->withErrors(['login' => 'Only administrators can access the admin dashboard.'])
                ->onlyInput('login');
        }

        Auth::guard('web')->login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.listings.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Signed out successfully.');
    }

    private function isAdminUser(User $user): bool
    {
        return $user->role === 'admin'
            || $user->roles->contains(static fn ($role): bool => $role->code === 'admin');
    }

    private function findAdminLoginUser(string $identifier): ?User
    {
        $query = User::query()->with('roles:id,code');
        $normalizedIdentifier = strtolower(trim($identifier));

        if ($normalizedIdentifier === self::FIXED_ADMIN_LOGIN) {
            return $query
                ->where(static function ($builder): void {
                    $builder
                        ->where('role', 'admin')
                        ->orWhereHas('roles', static function ($roleQuery): void {
                            $roleQuery->where('code', 'admin');
                        });
                })
                ->orderBy('id')
                ->first();
        }

        return $query
            ->where('email', $identifier)
            ->orWhere('student_id', $identifier)
            ->first();
    }
}
