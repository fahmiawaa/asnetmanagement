<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\MikrotikDevice;
use App\Models\PppoeAccount;
use App\Services\AuditLogService;
use App\Services\PppoeProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                    'overdue',
                    'isolated',
                    'stopped',
                    'suspended',
                    'unmapped',
                ]),
            ],
            'package_id' => 'nullable|integer|exists:internet_packages,id',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $status = $validated['status'] ?? '';
        $packageId = $validated['package_id'] ?? null;

        $customers = Customer::query()
            ->with(['internetPackage', 'pppoeAccount'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('whatsapp_number', 'like', "%{$search}%")
                        ->orWhereHas('pppoeAccount', function ($query) use ($search) {
                            $query->where('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($packageId, fn ($query) => $query->where('internet_package_id', $packageId))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statuses = [
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            'overdue' => 'Menunggak',
            'isolated' => 'Terisolir',
            'stopped' => 'Berhenti',
            'suspended' => 'Suspended',
            'unmapped' => 'Belum Dipetakan',
        ];
        $packages = InternetPackage::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('customers.index', compact(
            'customers',
            'search',
            'status',
            'statuses',
            'packageId',
            'packages'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $packages = InternetPackage::query()
            ->where('status', 'active')
            ->where('is_present', true)
            ->whereNotNull('mikrotik_device_id')
            ->orderBy('name')
            ->get([
                'id',
                'mikrotik_device_id',
                'name',
                'price',
                'mikrotik_profile',
            ]);

        $mikrotikDevices = MikrotikDevice::query()
            ->where('connection_status', '!=', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'connection_status']);
        $defaultMikrotikDeviceId = $mikrotikDevices->count() === 1
            ? $mikrotikDevices->first()->id
            : null;

        return view('customers.create', compact(
            'packages',
            'mikrotikDevices',
            'defaultMikrotikDeviceId'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        Request $request,
        PppoeProvisioningService $provisioningService,
        AuditLogService $auditLog
    ) {
        $validated = $request->validate([
            'customer_code' => 'required|string|max:50|unique:customers,customer_code',
            'mikrotik_device_id' => [
                'required',
                'integer',
                Rule::exists('mikrotik_devices', 'id')
                    ->where('connection_status', '!=', 'inactive')
                    ->whereNull('deleted_at'),
            ],
            'internet_package_id' => [
                'required',
                Rule::exists('internet_packages', 'id')
                    ->where('status', 'active')
                    ->where('is_present', true)
                    ->where(
                        'mikrotik_device_id',
                        $request->integer('mikrotik_device_id')
                    )
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'whatsapp_number' => 'required|string|max:20',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'installation_date' => 'nullable|date',
            'due_day' => 'required|integer|min:1|max:31',
            'notes' => 'nullable|string',
            'create_pppoe_account' => 'nullable|boolean',
            'pppoe_username' => [
                Rule::requiredIf($request->boolean('create_pppoe_account')),
                'nullable',
                'string',
                'max:255',
            ],
            'pppoe_password' => [
                Rule::requiredIf($request->boolean('create_pppoe_account')),
                'nullable',
                'string',
                'min:1',
                'max:255',
            ],
            'pppoe_service' => 'nullable|string|max:255',
            'pppoe_profile' => 'nullable|string|max:255',
            'provision_pppoe' => 'nullable|boolean',
        ]);

        $createPppoe = $request->boolean('create_pppoe_account');
        $pppoeDeviceId = $validated['mikrotik_device_id'] ?? null;
        $pppoeUsername = trim((string) ($validated['pppoe_username'] ?? ''));
        $packageProfile = InternetPackage::whereKey(
            $validated['internet_package_id']
        )->value('mikrotik_profile');
        $pppoeProfile = trim((string) ($validated['pppoe_profile'] ?? ''));
        $pppoeProfile = $pppoeProfile !== '' ? $pppoeProfile : $packageProfile;

        if (
            $createPppoe
            && PppoeAccount::query()
                ->where('mikrotik_device_id', $pppoeDeviceId)
                ->where('username', $pppoeUsername)
                ->exists()
        ) {
            return back()
                ->withErrors([
                    'pppoe_username' => 'Username PPPoE sudah digunakan pada perangkat MikroTik tersebut.',
                ])
                ->withInput();
        }

        $customerData = collect($validated)
            ->except([
                'create_pppoe_account',
                'mikrotik_device_id',
                'pppoe_username',
                'pppoe_password',
                'pppoe_service',
                'pppoe_profile',
                'provision_pppoe',
            ])
            ->all();

        [$customer, $pppoeAccount] = DB::transaction(function () use (
            $customerData,
            $createPppoe,
            $validated,
            $pppoeUsername,
            $pppoeDeviceId,
            $pppoeProfile,
            $provisioningService
        ) {
            $customer = Customer::create($customerData + [
                'status' => 'active',
            ]);

            $pppoeAccount = null;

            if ($createPppoe) {
                $pppoeAccount = PppoeAccount::create([
                    'mikrotik_device_id' => $pppoeDeviceId,
                    'username' => $pppoeUsername,
                    'encrypted_password' => $provisioningService->encryptPassword(
                        $validated['pppoe_password']
                    ),
                    'service' => $validated['pppoe_service'] ?: 'pppoe',
                    'profile' => $pppoeProfile,
                    'disabled' => false,
                    'is_present' => false,
                    'provision_status' => 'pending',
                    'provisioned_at' => null,
                    'provision_error' => null,
                    'comment' => 'Dibuat bersama pelanggan '.$customer->customer_code,
                ]);

                $customer->update([
                    'pppoe_account_id' => $pppoeAccount->id,
                ]);
            }

            return [$customer, $pppoeAccount];
        });

        $message = 'Pelanggan berhasil ditambahkan.';

        if ($pppoeAccount && $request->boolean('provision_pppoe')) {
            try {
                $provisioningService->provision(
                    $pppoeAccount,
                    $validated['pppoe_password']
                );

                $pppoeAccount->update([
                    'is_present' => true,
                    'provision_status' => 'provisioned',
                    'provisioned_at' => now(),
                    'provision_error' => null,
                    'last_synced_at' => now(),
                ]);

                $message .= ' Akun PPPoE berhasil dibuat langsung di MikroTik.';
            } catch (Throwable $exception) {
                report($exception);

                $pppoeAccount->update([
                    'provision_status' => 'failed',
                    'provision_error' => 'Provisioning ke MikroTik gagal. Periksa koneksi dan konfigurasi.',
                ]);

                $message .= ' Data akun PPPoE tersimpan di web, tetapi gagal dikirim ke MikroTik. Periksa konfigurasi lalu sinkronkan kembali.';
            }
        } elseif ($pppoeAccount) {
            $message .= ' Akun PPPoE tersimpan di web dan menunggu provisioning ke MikroTik.';
        }

        $auditLog->record(
            'customer.created',
            $customer,
            "Pelanggan {$customer->customer_code} dibuat.",
            ['pppoe_account_id' => $customer->pppoe_account_id]
        );

        return redirect()
            ->route('customers.index')
            ->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $customer->load([
            'internetPackage',
            'pppoeAccount.mikrotikDevice',
            'ontDevice:id,customer_id,serial_number,manufacturer,product_class,connection_status,rx_power_dbm,rx_power_quality',
            'invoices' => function ($query) {
                $query
                    ->with('payments')
                    ->latest('due_date');
            },
        ]);

        $billableInvoices = $customer->invoices
            ->filter(function ($invoice) {
                return $invoice->status !== 'cancelled';
            });

        $totalBilled = $billableInvoices
            ->sum('total_amount');

        $totalPaid = $billableInvoices
            ->flatMap(function ($invoice) {
                return $invoice->payments;
            })
            ->where('status', 'verified')
            ->sum('amount');

        $totalOutstanding = max(
            $totalBilled - $totalPaid,
            0
        );

        return view('customers.show', compact(
            'customer',
            'totalBilled',
            'totalPaid',
            'totalOutstanding'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        $packages = InternetPackage::where('status', 'active')
            ->orderBy('name')
            ->get();

        $pppoeAccounts = PppoeAccount::where(function ($query) use ($customer) {
            $query
                ->whereDoesntHave('customer')
                ->orWhere('id', $customer->pppoe_account_id);
        })
            ->where(function ($query) use ($customer) {
                $query
                    ->where('disabled', false)
                    ->where('is_present', true)
                    ->orWhere('id', $customer->pppoe_account_id);
            })
            ->orderBy('username')
            ->get();

        return view('customers.edit', compact(
            'customer',
            'packages',
            'pppoeAccounts'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        Request $request,
        Customer $customer,
        AuditLogService $auditLog
    ) {
        $validated = $request->validate([
            'customer_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'customer_code')
                    ->ignore($customer->id),
            ],
            'internet_package_id' => [
                'required',
                Rule::exists('internet_packages', 'id')
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'whatsapp_number' => 'required|string|max:20',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'installation_date' => 'nullable|date',
            'due_day' => 'required|integer|min:1|max:31',
            'notes' => 'nullable|string',
            'pppoe_account_id' => [
                'nullable',
                Rule::exists('pppoe_accounts', 'id'),
            ],
        ]);

        $pppoeAccountId = $validated['pppoe_account_id'] ?? null;

        if ($pppoeAccountId) {
            $pppoeAccount = PppoeAccount::find($pppoeAccountId);

            if (
                $pppoeAccount
                && $pppoeAccount->id !== $customer->pppoe_account_id
                && (! $pppoeAccount->is_present || $pppoeAccount->disabled)
            ) {
                return back()
                    ->withErrors([
                        'pppoe_account_id' => 'Akun PPPoE sedang disabled.',
                    ])
                    ->withInput();
            }

            $alreadyAssigned = Customer::where(
                'pppoe_account_id',
                $pppoeAccountId
            )
                ->where('id', '!=', $customer->id)
                ->exists();

            if ($alreadyAssigned) {
                return back()
                    ->withErrors([
                        'pppoe_account_id' => 'Akun PPPoE sudah digunakan customer lain.',
                    ])
                    ->withInput();
            }
        }

        $validated['pppoe_account_id'] = $pppoeAccountId;

        $customer->update($validated);

        $auditLog->record(
            'customer.updated',
            $customer,
            "Pelanggan {$customer->customer_code} diperbarui.",
            ['pppoe_account_id' => $customer->pppoe_account_id]
        );

        return redirect()
            ->route('customers.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer, AuditLogService $auditLog)
    {
        $customer->update([
            'status' => 'inactive',
        ]);

        $auditLog->record(
            'customer.deactivated',
            $customer,
            "Pelanggan {$customer->customer_code} dinonaktifkan."
        );

        return redirect()
            ->route('customers.index')
            ->with('success', 'Pelanggan berhasil dinonaktifkan tanpa menghapus histori.');
    }
}
