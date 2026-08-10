<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::orderBy('id', 'asc')->paginate(10);
        $departments = \App\Models\Department::all();
        return view('users.index', compact('users', 'departments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $departments = \App\Models\Department::all();
        return view('users.create', compact('departments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:50', 'unique:'.User::class],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', 'in:admin,manager,supervisor,staff'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        User::create([
            'name' => $request->name,
            'employee_code' => $request->employee_code,
            'email' => $request->email,
            'password' => $request->password, // Laravel 10+ handles hashing via 'hashed' cast
            'role' => $request->role,
            'department_id' => $request->department_id,
            // Default level based on role for compatibility
            'level' => match($request->role) {
                'admin' => 9,
                'manager' => 5,
                'supervisor' => 4,
                'staff' => 1,
                default => 1,
            },
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $departments = \App\Models\Department::all();
        return view('users.edit', compact('user', 'departments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:50', 'unique:users,employee_code,'.$user->id],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'string', 'in:admin,manager,supervisor,staff'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        $data = [
            'name' => $request->name,
            'employee_code' => $request->employee_code,
            'email' => $request->email,
            'role' => $request->role,
            'department_id' => $request->department_id,
            'level' => match($request->role) {
                'admin' => 9,
                'manager' => 5,
                'supervisor' => 4,
                'staff' => 1,
                default => 1,
            },
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->password; // Avoid double hashing
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
