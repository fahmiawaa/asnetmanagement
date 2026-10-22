<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Laporan Billing') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-xl font-semibold text-gray-800 mb-4">
                    Laporan Billing ASNet
                </h3>

                <form
                    method="GET"
                    action="{{ route('reports.index') }}"
                    class="mb-6"
                >
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label
                                for="start_date"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Dari Tanggal
                            </label>

                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                value="{{ $startDate }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >
                        </div>

                        <div>
                            <label
                                for="end_date"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Sampai Tanggal
                            </label>

                            <input
                                type="date"
                                name="end_date"
                                id="end_date"
                                value="{{ $endDate }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >
                        </div>

                        <div>
                            <button
                                type="submit"
                                style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer;"
                            >
                                Tampilkan Laporan
                            </button>

                            <a
                                href="{{ route('reports.export-csv', [
                                    'start_date' => $startDate,
                                    'end_date' => $endDate,
                                ]) }}"
                                style="background-color: #16a34a; color: white; padding: 10px 16px; border-radius: 6px; text-decoration: none; display: inline-block; margin-left: 8px;"
                            >
                                Export CSV
                            </a>
                        </div>
                    </div>

                    @error('start_date')
                        <p class="text-red-600 text-sm mt-2">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('end_date')
                        <p class="text-red-600 text-sm mt-2">
                            {{ $message }}
                        </p>
                    @enderror
                </form>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

                    <div class="bg-blue-100 rounded-lg p-4">
                        <p class="text-sm text-blue-800">
                            Total Invoice
                        </p>

                        <p class="text-3xl font-bold text-blue-900">
                            {{ $summary['totalInvoices'] }}
                        </p>
                    </div>

                    <div class="bg-green-100 rounded-lg p-4">
                        <p class="text-sm text-green-800">
                            Invoice Paid
                        </p>

                        <p class="text-3xl font-bold text-green-900">
                            {{ $summary['paidInvoices'] }}
                        </p>
                    </div>

                    <div class="bg-yellow-100 rounded-lg p-4">
                        <p class="text-sm text-yellow-800">
                            Invoice Unpaid
                        </p>

                        <p class="text-3xl font-bold text-yellow-900">
                            {{ $summary['unpaidInvoices'] }}
                        </p>
                    </div>

                    <div class="bg-red-100 rounded-lg p-4">
                        <p class="text-sm text-red-800">
                            Invoice Overdue
                        </p>

                        <p class="text-3xl font-bold text-red-900">
                            {{ $summary['overdueInvoices'] }}
                        </p>
                    </div>

                    <div class="bg-gray-100 rounded-lg p-4">
                        <p class="text-sm text-gray-700">
                            Invoice Cancelled
                        </p>

                        <p class="text-3xl font-bold text-gray-800">
                            {{ $summary['cancelledInvoices'] }}
                        </p>
                    </div>

                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-sm text-green-800">
                            Jumlah Payment Terverifikasi
                        </p>

                        <p class="text-2xl font-bold text-green-900">
                            {{ $summary['verifiedPayments'] }}
                        </p>
                    </div>

                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                        <p class="text-sm text-indigo-800">
                            Total Pembayaran Terverifikasi
                        </p>

                        <p class="text-2xl font-bold text-indigo-900">
                            Rp {{ number_format((float) $summary['totalPaymentAmount'], 0, ',', '.') }}
                        </p>
                    </div>

                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    Daftar Invoice Periode Terpilih
                </h3>

                @if ($invoices->isEmpty())
                    <p class="text-gray-600">
                        Tidak ada invoice pada periode yang dipilih.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border px-4 py-2 text-left">
                                        Invoice
                                    </th>

                                    <th class="border px-4 py-2 text-left">
                                        Pelanggan
                                    </th>

                                    <th class="border px-4 py-2 text-left">
                                        Jatuh Tempo
                                    </th>

                                    <th class="border px-4 py-2 text-left">
                                        Total
                                    </th>

                                    <th class="border px-4 py-2 text-left">
                                        Status
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr>
                                        <td class="border px-4 py-2">
                                            {{ $invoice->invoice_number }}
                                        </td>

                                        <td class="border px-4 py-2">
                                            {{ $invoice->customer->name ?? '-' }}
                                        </td>

                                        <td class="border px-4 py-2">
                                            {{ optional($invoice->due_date)->format('d-m-Y') ?? '-' }}
                                        </td>

                                        <td class="border px-4 py-2">
                                            Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}
                                        </td>

                                        <td class="border px-4 py-2">
                                            {{ $invoice->status }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $invoices->links() }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>