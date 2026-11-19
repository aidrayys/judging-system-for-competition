<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JudgeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.judges.index', [
            'judges' => User::where('role', User::ROLE_JUDGE)->orderBy('name')->get(),
            'admins' => User::where('role', User::ROLE_ADMIN)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $judge = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'role' => User::ROLE_JUDGE,
            'is_active' => true,
        ]);

        $this->audit->log('judge.create', 'Created judge account '.$judge->username, User::class, $judge->id);

        return back()->with('status', 'Judge account created for '.$judge->name.'.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== User::ROLE_JUDGE) {
            return back()->with('error', 'Only judge accounts can be reset here.');
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $data['password']]);

        $this->audit->log('judge.reset_password', 'Reset password for judge '.$user->username, User::class, $user->id);

        return back()->with('status', 'Password reset for '.$user->name.'. Please inform the judge securely.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== User::ROLE_JUDGE) {
            return back()->with('error', 'Only judge accounts can be enabled or disabled here.');
        }

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->log(
            'judge.toggle',
            ($user->is_active ? 'Enabled' : 'Disabled').' judge account '.$user->username,
            User::class,
            $user->id,
            ['is_active' => $user->is_active]
        );

        return back()->with('status', ($user->is_active ? 'Judge enabled' : 'Judge disabled').': '.$user->name.'.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== User::ROLE_JUDGE) {
            return back()->with('error', 'Only judge accounts can be edited here.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
        ]);

        $user->update($data);
        $this->audit->log('judge.update', 'Updated judge account '.$user->username, User::class, $user->id);

        return back()->with('status', 'Judge account updated.');
    }
}
