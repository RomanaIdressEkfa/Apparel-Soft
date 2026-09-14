<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderByDesc('created_at')->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_approved' => $user->is_approved,
            'approved_at' => $user->approved_at?->format('Y-m-d H:i'),
            'created_at' => $user->created_at?->format('Y-m-d H:i'),
        ]);

        return response()->json([
            'users' => $users,
            'pending' => $users->where('is_approved', false)->count(),
        ]);
    }

    public function approve(Request $request, User $user)
    {
        $user->update(['is_approved' => true, 'approved_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function revoke(Request $request, User $user)
    {
        if ($this->isSelf($request, $user)) {
            return response()->json(['error' => 'You cannot revoke your own access.'], 422);
        }

        $user->update(['is_approved' => false, 'approved_at' => null]);

        return response()->json(['ok' => true]);
    }

    public function setRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        if ($this->isSelf($request, $user) && $data['role'] !== 'admin') {
            return response()->json(['error' => 'You cannot remove your own admin rights.'], 422);
        }

        $user->update(['role' => $data['role']]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($this->isSelf($request, $user)) {
            return response()->json(['error' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }

    private function isSelf(Request $request, User $user): bool
    {
        return $request->user()->id === $user->id;
    }
}
