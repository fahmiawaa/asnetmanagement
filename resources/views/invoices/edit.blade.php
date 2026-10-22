<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Invoice') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div class="mb-6">
                        <h3 class="text-xl font-bold text-gray-800">
                            Edit {{ $invoice->invoice_number }}
                        </h3>

                        <p class="text-gray-600 mt-1">
                            Pelanggan:
                            {{ $invoice->customer->name ?? '-' }}
                        </p>

                        <p class="text-sm text-gray-500 mt-1">
                            Status invoice:
                            {{ $invoice->status }}
                        </p>
                    </div>

                    @if ($invoice->status === 'paid')
                        <div class="mb-6 p-4 bg-yellow-100 text-yellow-800 rounded-md">
                            Invoice yang sudah paid sebaiknya tidak diubah agar riwayat pembayaran tetap konsisten.
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('invoices.update', $invoice) }}"
                    >
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label
                                for="period_start"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Periode Mulai
                            </label>

                            <input
                                type="date"
                                name="period_start"
                                id="period_start"
                                value="{{ old('period_start', optional($invoice->period_start)->format('Y-m-d')) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('period_start')
                                <p class="text-red-600 text-sm mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label
                                for="period_end"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Periode Selesai
                            </label>

                            <input
                                type="date"
                                name="period_end"
                                id="period_end"
                                value="{{ old('period_end', optional($invoice->period_end)->format('Y-m-d')) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('period_end')
                                <p class="text-red-600 text-sm mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label
                                for="due_date"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Tanggal Jatuh Tempo
                            </label>

                            <input
                                type="date"
                                name="due_date"
                                id="due_date"
                                value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('due_date')
                                <p class="text-red-600 text-sm mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label
                                for="total_amount"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Total Tagihan
                            </label>

                            <input
                                type="number"
                                name="total_amount"
                                id="total_amount"
                                value="{{ old('total_amount', $invoice->total_amount) }}"
                                min="0"
                                step="0.01"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('total_amount')
                                <p class="text-red-600 text-sm mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label
                                for="notes"
                                class="block font-medium text-sm text-gray-700"
                            >
                                Catatan
                            </label>

                            <textarea
                                name="notes"
                                id="notes"
                                rows="4"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >{{ old('notes', $invoice->notes) }}</textarea>

                            @error('notes')
                                <p class="text-red-600 text-sm mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <button
                                type="submit"
                                style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer;"
                            >
                                Simpan Perubahan
                            </button>

                            <a
                                href="{{ route('invoices.show', $invoice) }}"
                                style="background-color: #e5e7eb; color: #1f2937; padding: 10px 16px; border-radius: 6px; display: inline-block; text-decoration: none; margin-left: 8px;"
                            >
                                Batal
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>