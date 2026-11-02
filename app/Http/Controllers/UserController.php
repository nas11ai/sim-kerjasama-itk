<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
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

        return Inertia::render('Users/EditPage', [
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
}
