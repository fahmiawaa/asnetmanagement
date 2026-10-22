<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Invoice') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
                    <div>
                        <p class="text-sm text-gray-500">Invoice pelanggan</p>
                        <h3 class="text-2xl font-bold text-gray-800">{{ $invoice->invoice_number }}</h3>
                    </div>
                    <a href="{{ route('customer.invoices.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md text-center">
                        Kembali
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3">Informasi tagihan</h4>
                        <div class="space-y-2 text-gray-700">
                            <p><strong>Pelanggan:</strong> {{ $customer->name }}</p>
                            <p><strong>Paket:</strong> {{ $invoice->customer->internetPackage->name ?? 'Belum ditentukan' }}</p>
                            <p><strong>Periode:</strong> {{ optional($invoice->period_start)->format('d-m-Y') ?? '-' }} s/d {{ optional($invoice->period_end)->format('d-m-Y') ?? '-' }}</p>
                            <p><strong>Jatuh tempo:</strong> {{ optional($invoice->due_date)->format('d-m-Y') ?? '-' }}</p>
                            <p><strong>Status:</strong> {{ ucfirst($invoice->status) }}</p>
                        </div>
                    </div>

                    <div class="border rounded-lg p-4">
                        <p class="text-sm text-gray-500">Total invoice</p>
                        <p class="text-3xl font-bold mt-2">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</p>

                        @php
                            $hasPendingPayment = $invoice->payments->contains('status', 'pending');
                        @endphp

                        @if (in_array($invoice->status, ['unpaid', 'overdue'], true) && !$hasPendingPayment)
                            <a href="{{ route('customer.payments.create', ['invoice_id' => $invoice->id]) }}" class="inline-block mt-5 bg-blue-600 text-white px-4 py-2 rounded-md">
                                Ajukan Pembayaran
                            </a>
                        @elseif ($hasPendingPayment)
                            <p class="mt-5 text-amber-700">Pembayaran Anda sedang menunggu verifikasi.</p>
                        @endif
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">Riwayat pembayaran invoice</h4>
                    @if ($invoice->payments->isEmpty())
                        <p class="text-gray-600">Belum ada pembayaran.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border px-4 py-3 text-left">Tanggal</th>
                                        <th class="border px-4 py-3 text-left">Nominal</th>
                                        <th class="border px-4 py-3 text-left">Metode</th>
                                        <th class="border px-4 py-3 text-left">Status</th>
                                        <th class="border px-4 py-3 text-left">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoice->payments as $payment)
                                        <tr>
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
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>