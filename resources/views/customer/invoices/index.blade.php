<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Invoice Saya') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-6">
                        <div>
                            <p class="text-sm text-gray-500">Tagihan untuk {{ $customer->name }}</p>
                            <h3 class="text-2xl font-bold mt-1">Daftar invoice</h3>
                        </div>
                        <a href="{{ route('customer.payments.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md text-center">
                            Bayar Invoice
                        </a>
                    </div>

                    @if ($invoices->isEmpty())
                        <div class="border rounded-lg p-6 text-center text-gray-600">
                            Belum ada invoice untuk akun Anda.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border px-4 py-3 text-left">Nomor</th>
                                        <th class="border px-4 py-3 text-left">Jatuh tempo</th>
                                        <th class="border px-4 py-3 text-left">Total</th>
                                        <th class="border px-4 py-3 text-left">Status</th>
                                        <th class="border px-4 py-3 text-left">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoices as $invoice)
                                        <tr>
                                            <td class="border px-4 py-3">
                                                <a href="{{ route('customer.invoices.show', $invoice) }}" class="text-blue-600 hover:underline">
                                                    {{ $invoice->invoice_number }}
                                                </a>
                                            </td>
                                            <td class="border px-4 py-3">{{ optional($invoice->due_date)->format('d-m-Y') ?? '-' }}</td>
                                            <td class="border px-4 py-3">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
                                            <td class="border px-4 py-3">{{ ucfirst($invoice->status) }}</td>
                                            <td class="border px-4 py-3">
                                                <div class="flex flex-wrap gap-2">
                                                    <a href="{{ route('customer.invoices.show', $invoice) }}" class="text-blue-600 hover:underline">
                                                        Detail
                                                    </a>
                                                    @if (in_array($invoice->status, ['unpaid', 'overdue'], true))
                                                        <a href="{{ route('customer.payments.create', ['invoice_id' => $invoice->id]) }}" class="text-green-600 hover:underline">
                                                            Bayar
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $invoices->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>