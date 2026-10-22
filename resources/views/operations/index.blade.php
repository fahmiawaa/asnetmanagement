<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monitoring Operasional</h2>
            <span @class([
                'px-3 py-1 rounded-full text-sm font-semibold',
                'bg-green-100 text-green-800' => $report['status'] === 'healthy',
                'bg-yellow-100 text-yellow-800' => $report['status'] === 'degraded',
                'bg-red-100 text-red-800' => $report['status'] === 'critical',
            ])>
                {{ strtoupper($report['status']) }}
            </span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach ($report['checks'] as $check)
                    <div @class([
                        'rounded-lg border p-5',
                        'bg-green-50 border-green-200' => $check['status'] === 'healthy',
                        'bg-yellow-50 border-yellow-200' => $check['status'] === 'warning',
                        'bg-red-50 border-red-200' => $check['status'] === 'critical',
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold text-gray-900">{{ $check['label'] }}</h3>
                            <span @class([
                                'text-xs font-semibold px-2 py-1 rounded-full',
                                'bg-green-100 text-green-800' => $check['status'] === 'healthy',
                                'bg-yellow-100 text-yellow-800' => $check['status'] === 'warning',
                                'bg-red-100 text-red-800' => $check['status'] === 'critical',
                            ])>{{ strtoupper($check['status']) }}</span>
                        </div>
                        <p class="mt-3 text-sm text-gray-700">{{ $check['message'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Status MikroTik</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-3 pr-4">Perangkat</th>
                                <th class="py-3 pr-4">Host</th>
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3">Sinkronisasi terakhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse ($devices as $device)
                                <tr>
                                    <td class="py-3 pr-4 font-medium">{{ $device->name }}</td>
                                    <td class="py-3 pr-4">{{ $device->host }}</td>
                                    <td class="py-3 pr-4">{{ $device->connection_status }}</td>
                                    <td class="py-3">{{ $device->last_sync_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-gray-500">Belum ada perangkat MikroTik.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aksi isolir/restore gagal</h3>
                    <div class="space-y-3">
                        @forelse ($failedActions as $action)
                            <div class="border border-red-100 rounded p-3">
                                <p class="font-medium">{{ $action->customer?->name ?? 'Pelanggan tidak tersedia' }} — {{ $action->action }}</p>
                                <p class="text-sm text-red-700 mt-1">{{ $action->last_error ?: 'Tidak ada detail error.' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Tidak ada aksi gagal.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Queue job gagal</h3>
                    <div class="space-y-3">
                        @forelse ($failedJobs as $job)
                            <div class="border border-red-100 rounded p-3">
                                <p class="font-medium">{{ $job->queue }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ $job->failed_at }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Tidak ada job gagal.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>