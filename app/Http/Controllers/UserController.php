<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('roles')->get();
        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::all();
        return inertia('Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        $user->assignRole($data['role']);

        return to_route('admin.users.index')->with('success', 'User berhasil dibuat');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user)
    {
        if ($user->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            return to_route('admin.users.index')->with('error', 'Anda tidak berwenang untuk mengedit pengguna ini');
        }

        $roles = Role::all();
        $permissions = Permission::all();
        $user = User::with(['roles', 'permissions'])->find($user->id);
        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        if ($user->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            return to_route('admin.users.index')->with('error', 'Anda tidak berwenang untuk memperbarui pengguna ini');
        }

        $data = $request->validated();

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = bcrypt($data['password']);
        }

        $user->update($updateData);
        $user->syncRoles($data['role']);
        $user->syncPermissions($data['permissions'] ?? []);

        return to_route('admin.users.index')->with('success', 'User berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request, User $user)
    {
        $auth = $request->user();

        if ($user->hasAnyRole(['Super Admin', 'Admin']) && !$auth->hasRole('Super Admin')) {
            return to_route('admin.users.index')->with('error', 'Anda tidak berwenang untuk menghapus pengguna ini');
        }

        $user->delete();

        return to_route('admin.users.index')->with('success', 'User berhasil dihapus');
    }
}
