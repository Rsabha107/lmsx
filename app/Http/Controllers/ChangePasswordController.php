<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** The forced change after signing in with an admin-set temporary password. */
class ChangePasswordController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ChangePassword', ['email' => $request->user()->email]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8), function (string $a, mixed $v, \Closure $fail) use ($user) {
                if (Hash::check($v, $user->password)) {
                    $fail('Choose a password different from the temporary one.');
                }
            }],
        ]);

        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        $request->session()->regenerate();
        AuditLog::change('Temporary password replaced', $user->email, [], $user);

        return redirect()->route('dashboard')->with('success', 'Your new password is set.');
    }
}
