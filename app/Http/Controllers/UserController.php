<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->orderBy('name')->paginate(20);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'role'     => ['required', Rule::exists('roles', 'name')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'phone'     => $validated['phone'] ?? null,
            'password'  => $validated['password'], // hashed by the model cast
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('users.index')
            ->with('success', "Staff account for {$user->name} created successfully.");
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => 'nullable|string|max:20',
            'role'     => ['required', Rule::exists('roles', 'name')],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;

        // Only update password if a new one was entered
        if (!empty($validated['password'])) {
            $user->password = $validated['password']; // hashed by the model cast
        }

        $user->save();

        // Sync to a single role (matches how the dashboards read one role)
        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')
            ->with('success', "{$user->name}'s account updated successfully.");
    }

    public function toggleActive(User $user)
    {
        // Guard: an admin cannot deactivate their own account
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', "You cannot deactivate your own account.");
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $state = $user->is_active ? 'activated' : 'deactivated';

        return redirect()->route('users.index')
            ->with('success', "{$user->name}'s account has been {$state}.");
    }

    public function destroy(User $user)
    {
        // Guard: an admin cannot delete their own account
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', "You cannot delete your own account.");
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "{$name}'s account has been deleted.");
    }
}