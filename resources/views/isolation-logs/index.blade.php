<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Log Isolir dan Restore') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <h3 class="text-lg font-semibold mb-4">
                        Histori Simulasi Isolir
                    </h3>

                    <form method="GET" action="{{ route('isolation-logs.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-6">
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Cari customer, invoice, catatan..."
                            class="border-gray-300 rounded-md shadow-sm md:col-span-2"
                        >

                        <select name="action" class="border-gray-300 rounded-md shadow-sm">
                            <option value="">Semua tindakan</option>
                            <option value="isolate" @selected($action === 'isolate')>Isolate</option>
                            <option value="restore" @selected($action === 'restore')>Restore</option>
                        </select>

                        <select name="approval_status" class="border-gray-300 rounded-md shadow-sm">
                            <option value="">Semua approval</option>
                            <option value="pending" @selected($approvalStatus === 'pending')>Pending</option>
                            <option value="approved" @selected($approvalStatus === 'approved')>Approved</option>
                            <option value="processing" @selected($approvalStatus === 'processing')>Processing</option>
                            <option value="rejected" @selected($approvalStatus === 'rejected')>Rejected</option>
                            <option value="executed" @selected($approvalStatus === 'executed')>Executed</option>
                            <option value="failed" @selected($approvalStatus === 'failed')>Failed</option>
                        </select>

                        <select name="execution_mode" class="border-gray-300 rounded-md shadow-sm">
                            <option value="">Semua mode</option>
                            <option value="simulation" @selected($executionMode === 'simulation')>Simulation</option>
                            <option value="real" @selected($executionMode === 'real')>Real</option>
                        </select>

                        <div class="flex gap-2">
                            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-md">
                                Filter
                            </button>
                            <a href="{{ route('isolation-logs.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md">
                                Reset
                            </a>
                        </div>

                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="border-gray-300 rounded-md shadow-sm">
                        <input type="date" name="date_to" value="{{ $dateTo }}" class="border-gray-300 rounded-md shadow-sm">
                    </form>

                    @if ($logs->isEmpty())
                        <p class="text-gray-600">
                            Belum ada log isolir atau restore.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border px-4 py-2 text-left">
                                            Pelanggan
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Invoice
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Tindakan
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Status Lama
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Status Baru
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Mode
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Approval
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Aksi
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Waktu
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Retry
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Catatan
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($logs as $log)
                                        <tr>
                                            <td class="border px-4 py-2">
                                                {{ $log->customer->name ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $log->invoice->invoice_number ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $log->action }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $log->old_status ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $log->new_status ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $log->execution_mode }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $log->approval_status }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                @if ($log->approval_status === 'pending')
                                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">

                                                        <form
                                                            action="{{ route('isolation-logs.approve', $log) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Setujui kandidat isolir ini?')"
                                                        >
                                                            @csrf

                                                            <button
                                                                type="submit"
                                                                style="background-color: #16a34a; color: white; padding: 6px 10px; border-radius: 5px; border: none; cursor: pointer;"
                                                            >
                                                                Setujui
                                                            </button>
                                                        </form>

                                                        <form
                                                            action="{{ route('isolation-logs.reject', $log) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Tolak kandidat isolir ini?')"
                                                        >
                                                            @csrf

                                                            <button
                                                                type="submit"
                                                                style="background-color: #dc2626; color: white; padding: 6px 10px; border-radius: 5px; border: none; cursor: pointer;"
                                                            >
                                                                Tolak
                                                            </button>
                                                        </form>

                                                    </div>
                                                @else
                                                    <span class="text-gray-600">
                                                        Sudah diproses
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ optional($log->executed_at)->format('d-m-Y H:i') ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                <div>Percobaan: {{ $log->attempt_count }}</div>
                                                <div>
                                                    Berikutnya:
                                                    {{ optional($log->next_retry_at)->format('d-m-Y H:i') ?? '-' }}
                                                </div>
                                            </td>

                                            <td class="border px-4 py-2">
                                                @if ($log->last_error)
                                                    <div class="text-red-700">
                                                        {{ $log->last_error }}
                                                    </div>
                                                @endif
                                                <div>{{ $log->notes ?? '-' }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $logs->links() }}</div>
                    @endif

                </div>

                @if (session('success'))
                    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-md">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-md">
                        {{ session('error') }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>