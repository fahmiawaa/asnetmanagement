<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Invoice') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
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
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">
                            {{ $invoice->invoice_number }}
                        </h3>

                        <p class="text-gray-600">
                            Detail tagihan pelanggan
                        </p>
                    </div>

                    <a
                        href="{{ route('invoices.index') }}"
                        style="background-color: #e5e7eb; color: #1f2937; padding: 10px 16px; border-radius: 6px; text-decoration: none;"
                    >
                        Kembali
                    </a>

                    @if (in_array($invoice->status, ['unpaid', 'overdue']))
                        <form
                            action="{{ route('invoices.cancel', $invoice) }}"
                            method="POST"
                            style="display: inline-block; margin-left: 8px;"
                            onsubmit="return confirm('Apakah Anda yakin ingin membatalkan invoice ini?')"
                        >
                            @csrf

                            <button
                                type="submit"
                                style="background-color: #dc2626; color: white; padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer;"
                            >
                                Batalkan Invoice
                            </button>
                        </form>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3">
                            Informasi Invoice
                        </h4>

                        <div class="space-y-2">
                            <p>
                                <strong>Nomor Invoice:</strong>
                                {{ $invoice->invoice_number }}
                            </p>

                            <p>
                                <strong>Periode:</strong>
                                {{ optional($invoice->period_start)->format('d-m-Y') ?? '-' }}
                                sampai
                                {{ optional($invoice->period_end)->format('d-m-Y') ?? '-' }}
                            </p>

                            <p>
                                <strong>Jatuh Tempo:</strong>
                                {{ optional($invoice->due_date)->format('d-m-Y') ?? '-' }}
                            </p>

                            <p>
                                <strong>Total:</strong>
                                Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}
                            </p>

                            <p>
                                <strong>Status:</strong>
                                {{ $invoice->status }}
                            </p>

                            <p>
                                <strong>Dibayar Pada:</strong>
                                {{ optional($invoice->paid_at)->format('d-m-Y H:i') ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3">
                            Informasi Pelanggan
                        </h4>

                        <div class="space-y-2">
                            <p>
                                <strong>Kode Pelanggan:</strong>
                                {{ $invoice->customer->customer_code ?? '-' }}
                            </p>

                            <p>
                                <strong>Nama:</strong>
                                {{ $invoice->customer->name ?? '-' }}
                            </p>

                            <p>
                                <strong>Nomor WhatsApp:</strong>
                                {{ $invoice->customer->whatsapp_number ?? '-' }}
                            </p>

                            <p>
                                <strong>Paket Internet:</strong>
                                {{ $invoice->customer->internetPackage->name ?? '-' }}
                            </p>

                            <p>
                                <strong>Kecepatan:</strong>
                                {{ $invoice->customer->internetPackage->download_speed ?? '-' }}
                            </p>
                        </div>
                    </div>

                </div>

                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">
                        Catatan Invoice
                    </h4>

                    <div class="border rounded-lg p-4 text-gray-700">
                        {{ $invoice->notes ?? 'Tidak ada catatan.' }}
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">
                        Riwayat Pembayaran
                    </h4>

                    @if ($invoice->payments->isEmpty())
                        <p class="text-gray-600">
                            Belum ada pembayaran untuk invoice ini.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border px-4 py-2 text-left">
                                            Tanggal
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Nominal
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Metode
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Status
                                        </th>

                                        <th class="border px-4 py-2 text-left">
                                            Diverifikasi Oleh
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($invoice->payments as $payment)
                                        <tr>
                                            <td class="border px-4 py-2">
                                                {{ optional($payment->payment_date)->format('d-m-Y') ?? '-' }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $payment->method }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $payment->status }}
                                            </td>

                                            <td class="border px-4 py-2">
                                                {{ $payment->verifier->name ?? '-' }}
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