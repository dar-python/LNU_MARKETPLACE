<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminSessionController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $email = is_string($validated['email'] ?? null) ? $validated['email'] : null;
        $studentId = is_string($validated['student_id'] ?? null) ? $validated['student_id'] : null;
        $password = (string) $validated['password'];

        $user = $this->findUserForLogin($email, $studentId);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Invalid credentials.'],
            ]);
        }

        if ($user->isBlockedFromLogin()) {
            throw ValidationException::withMessages([
                'identifier' => ['Account suspended.'],
            ]);
        }

        if ($user->email !== null && $user->email_verified_at === null) {
            throw ValidationException::withMessages([
                'identifier' => ['Email not verified yet.'],
            ]);
        }

        if (! AdminAccess::allows($user)) {
            throw ValidationException::withMessages([
                'identifier' => ['Only admins can sign in here.'],
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route('admin.listings.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function findUserForLogin(?string $email, ?string $studentId): ?User
    {
        $query = User::query()->with('roles');

        if (is_string($email) && $email !== '') {
            return $query->where('email', $email)->first();
        }

        if (is_string($studentId) && $studentId !== '') {
            return $query->where('student_id', $studentId)->first();
        }

        return null;
    }
}
