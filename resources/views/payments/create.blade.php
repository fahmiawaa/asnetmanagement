<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Pembayaran') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <form
                        method="POST"
                        action="{{ route('payments.store') }}"
                        enctype="multipart/form-data"
                    >
                        @csrf

                        <div class="mb-4">
                            <label for="invoice_id" class="block font-medium text-sm text-gray-700">
                                Invoice
                            </label>

                            <select
                                name="invoice_id"
                                id="invoice_id"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Pilih Invoice</option>

                                @foreach ($invoices as $invoice)
                                    <option
                                        value="{{ $invoice->id }}"
                                        @selected(old('invoice_id') == $invoice->id)
                                    >
                                        {{ $invoice->invoice_number }}
                                        -
                                        {{ $invoice->customer->name ?? '-' }}
                                        -
                                        Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>

                            @error('invoice_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="payment_date" class="block font-medium text-sm text-gray-700">
                                Tanggal Pembayaran
                            </label>

                            <input
                                type="date"
                                name="payment_date"
                                id="payment_date"
                                value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('payment_date')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="amount" class="block font-medium text-sm text-gray-700">
                                Nominal Pembayaran
                            </label>

                            <input
                                type="number"
                                name="amount"
                                id="amount"
                                value="{{ old('amount') }}"
                                min="0.01"
                                step="0.01"
                                placeholder="Contoh: 100000"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('amount')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="method" class="block font-medium text-sm text-gray-700">
                                Metode Pembayaran
                            </label>

                            <select
                                name="method"
                                id="method"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Pilih Metode</option>

                                <option value="cash" @selected(old('method') === 'cash')>
                                    Tunai
                                </option>

                                <option value="bank_transfer" @selected(old('method') === 'bank_transfer')>
                                    Transfer Bank
                                </option>

                                <option value="e_wallet" @selected(old('method') === 'e_wallet')>
                                    E-Wallet
                                </option>

                                <option value="qris" @selected(old('method') === 'qris')>
                                    QRIS (aktif 24 jam)
                                </option>
                            </select>

                            <p class="text-sm text-gray-500 mt-1">
                                QRIS menggunakan QRIS statis merchant dan memiliki jendela pembayaran 24 jam sejak permintaan dibuat.
                            </p>

                            @error('method')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="proof_path" class="block font-medium text-sm text-gray-700">
                                Bukti Pembayaran
                            </label>

                            <input
                                type="file"
                                name="proof_path"
                                id="proof_path"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >

                            <p class="text-sm text-gray-500 mt-1">
                                Format yang diperbolehkan: JPG, PNG, atau PDF.
                            </p>

                            @error('proof_path')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="notes" class="block font-medium text-sm text-gray-700">
                                Catatan
                            </label>

                            <textarea
                                name="notes"
                                id="notes"
                                rows="3"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >{{ old('notes') }}</textarea>

                            @error('notes')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <input type="hidden" name="status" value="pending">

                        <div>
                            <button
                                type="submit"
                                style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer;"
                            >
                                Simpan Pembayaran
                            </button>

                            <a
                                href="{{ route('payments.index') }}"
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