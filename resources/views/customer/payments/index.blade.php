<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Pembayaran Saya') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-6">
                    <div>
                        <p class="text-sm text-gray-500">Riwayat pengajuan pembayaran</p>
                        <h3 class="text-2xl font-bold mt-1">Pembayaran Saya</h3>
                    </div>
                    <a href="{{ route('customer.payments.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md text-center">
                        Bayar Invoice
                    </a>
                </div>

                @if ($payments->isEmpty())
                    <div class="border rounded-lg p-6 text-center text-gray-600">
                        Belum ada pengajuan pembayaran.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border px-4 py-3 text-left">Invoice</th>
                                    <th class="border px-4 py-3 text-left">Tanggal</th>
                                    <th class="border px-4 py-3 text-left">Nominal</th>
                                    <th class="border px-4 py-3 text-left">Metode</th>
                                    <th class="border px-4 py-3 text-left">Status</th>
                                    <th class="border px-4 py-3 text-left">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td class="border px-4 py-3">{{ $payment->invoice->invoice_number ?? '-' }}</td>
                                        <td class="border px-4 py-3">{{ optional($payment->payment_date)->format('d-m-Y') ?? '-' }}</td>
                                        <td class="border px-4 py-3">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                                        <td class="border px-4 py-3">{{ match ($payment->method) {
                                            'bank_transfer' => 'Transfer Bank',
                                            'e_wallet' => 'E-Wallet',
                                            'qris' => 'QRIS',
                                            default => ucfirst($payment->method),
                                        } }}</td>
                                        <td class="border px-4 py-3">{{ ucfirst($payment->status) }}</td>
                                        <td class="border px-4 py-3">
                                            <a href="{{ route('customer.payments.show', $payment) }}" class="text-blue-600 hover:underline">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        {{ $payments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>