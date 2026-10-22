<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'role' => 'nullable|in:owner,admin,teknisi,pelanggan',
            'active' => 'nullable|in:0,1',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $role = $validated['role'] ?? '';
        $active = $validated['active'] ?? '';

        $users = User::query()
            ->with('customer')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($active !== '', fn ($query) => $query->where('active', (bool) $active))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users', 'search', 'role', 'active'));
    }

    public function create()
    {
        $customers = Customer::query()
            ->whereDoesntHave('user')
            ->orderBy('name')
            ->get(['id', 'customer_code', 'name']);

        return view('users.create', compact('customers'));
    }

    public function store(Request $request, AuditLogService $auditLog)
    {
        $validated = $this->validated($request, true);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'customer_id' => $validated['customer_id'] ?? null,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $auditLog->record(
            'user.created',
            $user,
            "User {$user->email} dibuat.",
            ['role' => $user->role, 'customer_id' => $user->customer_id]
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    public function edit(User $user)
    {
        $customers = Customer::query()
            ->where(function ($query) use ($user) {
                $query
                    ->whereDoesntHave('user')
                    ->orWhere('id', $user->customer_id);
            })
            ->orderBy('name')
            ->get(['id', 'customer_code', 'name']);

        return view('users.edit', compact('user', 'customers'));
    }

    public function update(
        Request $request,
        User $user,
        AuditLogService $auditLog
    ) {
        $validated = $this->validated($request, false, $user);

        $newRole = $validated['role'];
        $deactivating = array_key_exists('active', $validated)
            && ! $validated['active'];

        if ($user->is(auth()->user()) && $deactivating) {
            throw ValidationException::withMessages([
                'active' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ]);
        }

        if (
            $user->role === 'owner'
            && ($newRole !== 'owner' || $deactivating)
            && User::query()->where('role', 'owner')->where('active', true)->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'role' => 'Minimal harus ada satu owner aktif.',
            ]);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'role' => $newRole,
            'customer_id' => $validated['customer_id'] ?? null,
            'active' => $validated['active'] ?? true,
            ...(! empty($validated['password'])
                ? ['password' => Hash::make($validated['password'])]
                : []),
        ]);

        $auditLog->record(
            'user.updated',
            $user,
            "User {$user->email} diperbarui.",
            ['role' => $user->role, 'active' => $user->active]
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function toggle(User $user, AuditLogService $auditLog)
    {
        if ($user->is(auth()->user()) && $user->active) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if (
            $user->role === 'owner'
            && $user->active
            && User::query()->where('role', 'owner')->where('active', true)->count() <= 1
        ) {
            return back()->with('error', 'Minimal harus ada satu owner aktif.');
        }

        $user->update(['active' => ! $user->active]);

        $auditLog->record(
            $user->active ? 'user.activated' : 'user.deactivated',
            $user,
            "User {$user->email} ".($user->active ? 'diaktifkan.' : 'dinonaktifkan.')
        );

        return back()->with('success', 'Status user berhasil diperbarui.');
    }

    private function validated(
        Request $request,
        bool $creating,
        ?User $user = null
    ): array {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'role' => 'required|in:owner,admin,teknisi,pelanggan',
            'customer_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'pelanggan'),
                'nullable',
                'integer',
                Rule::exists('customers', 'id'),
                Rule::unique('users', 'customer_id')->ignore($user?->id),
            ],
            'active' => 'nullable|boolean',
        ];

        if ($creating) {
            $rules['password'] = 'required|string|min:12|confirmed';
        } else {
            $rules['password'] = 'nullable|string|min:12|confirmed';
        }

        return $request->validate($rules);
    }
}
