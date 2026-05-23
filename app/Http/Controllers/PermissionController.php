<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $permissions = Permission::all();

        $user = $request->user();

        return inertia('Permissions/Index', [
            'permissions' => $permissions,
            'can' => [
                'create' => $user->can('create permission'),
                'update' => $user->can('update permission'),
                'delete' => $user->can('delete permission'),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ], [
            'name.required' => 'Hak akses harus diisi.',
            'name.unique' => 'Hak akses sudah digunakan.',
        ]);

        Permission::create([
            'name' => $validated['name'],
        ]);

        return to_route('admin.permissions.index')->with('success', 'Hak akses berhasil dibuat.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,'.$permission->id],
        ], [
            'name.required' => 'Hak akses harus diisi.',
            'name.unique' => 'Hak akses sudah digunakan.',
        ]);

        $permission->update([
            'name' => $validated['name'],
        ]);

        return redirect()->route('admin.permissions.index')->with('success', 'Hak akses berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Permission $permission)
    {
        if ($permission->roles()->count() > 0) {
            return redirect()->route('admin.permissions.index')->with('error', 'Tidak dapat menghapus hak akses: hak akses ini sedang digunakan oleh satu atau lebih role.');
        }
        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('success', 'Hak akses berhasil dihapus.');
    }
}
