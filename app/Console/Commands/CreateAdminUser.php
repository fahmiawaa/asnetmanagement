<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {email : Email administrator}
                            {--name=Administrator : Nama user}
                            {--role=admin : Role owner|admin|teknisi|pelanggan}
                            {--customer_id= : ID customer jika role pelanggan}';

    protected $description = 'Membuat administrator secara aman melalui CLI';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = strtolower(trim((string) $this->option('role')));
        $allowedRoles = ['owner', 'admin', 'teknisi', 'pelanggan'];

        if (! in_array($role, $allowedRoles, true)) {
            $this->error('Role harus owner, admin, teknisi, atau pelanggan.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('User dengan email tersebut sudah ada.');

            return self::FAILURE;
        }

        $password = $this->secret('Password administrator');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Password minimal 12 karakter.');

            return self::FAILURE;
        }

        $customerId = $this->option('customer_id');

        if ($role === 'pelanggan' && (! $customerId || ! Customer::find($customerId))) {
            $this->error('Role pelanggan wajib memiliki customer_id yang valid.');

            return self::FAILURE;
        }

        if ($role === 'pelanggan' && User::where('customer_id', $customerId)->exists()) {
            $this->error('Customer tersebut sudah memiliki akun login.');

            return self::FAILURE;
        }

        User::create([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'customer_id' => $role === 'pelanggan' ? $customerId : null,
            'email_verified_at' => now(),
        ]);

        $this->info("User {$role} berhasil dibuat.");

        return self::SUCCESS;
    }
}
