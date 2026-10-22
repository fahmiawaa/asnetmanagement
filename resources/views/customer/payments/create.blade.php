<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ajukan Pembayaran') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="mb-6">
                    <p class="text-sm text-gray-500">Pembayaran aman untuk akun {{ $customer->name }}</p>
                    <h3 class="text-2xl font-bold mt-1">Ajukan pembayaran invoice</h3>
                    <p class="text-gray-600 mt-2">
                        Nominal diambil otomatis dari invoice dan tidak dapat diubah oleh pelanggan.
                    </p>
                </div>

                @if ($invoices->isEmpty())
                    <div class="border rounded-lg p-5 text-gray-700">
                        Tidak ada invoice yang dapat dibayar. Pastikan tidak ada pengajuan pembayaran yang masih menunggu verifikasi.
                    </div>
                @else
                    <form method="POST" action="{{ route('customer.payments.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-5">
                            <label for="invoice_id" class="block font-medium text-sm text-gray-700">Invoice</label>
                            <select name="invoice_id" id="invoice_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                                <option value="">Pilih invoice</option>
                                @foreach ($invoices as $invoice)
                                    <option value="{{ $invoice->id }}" @selected(old('invoice_id', $invoiceId) == $invoice->id)>
                                        {{ $invoice->invoice_number }} — Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }} — jatuh tempo {{ optional($invoice->due_date)->format('d-m-Y') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('invoice_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-5">
                            <label for="method" class="block font-medium text-sm text-gray-700">Metode pembayaran</label>
                            <select name="method" id="method" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                                <option value="">Pilih metode</option>
                                <option value="bank_transfer" @selected(old('method') === 'bank_transfer')>Transfer Bank</option>
                                <option value="e_wallet" @selected(old('method') === 'e_wallet')>E-Wallet</option>
                                @php
                                    $qrisImagePath = config('payments.qris.image_path');
                                    $qrisAvailable = config('payments.qris.enabled')
                                        && is_string($qrisImagePath)
                                        && is_file(public_path($qrisImagePath));
                                @endphp
                                <option value="qris" @selected(old('method') === 'qris') @disabled(!$qrisAvailable)>
                                    QRIS (aktif {{ config('payments.qris.expiration_hours', 24) }} jam)
                                </option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">
                                @if ($qrisAvailable)
                                    QRIS akan aktif {{ config('payments.qris.expiration_hours', 24) }} jam sejak pengajuan dibuat.
                                @else
                                    QRIS belum dikonfigurasi oleh pengelola. Gunakan transfer bank atau e-wallet.
                                @endif
                            </p>
                            @error('method') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-5">
                            <label for="proof_path" class="block font-medium text-sm text-gray-700">Bukti pembayaran</label>
                            <input type="file" name="proof_path" id="proof_path" accept=".jpg,.jpeg,.png,.pdf" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <p class="text-sm text-gray-500 mt-1">Opsional. JPG, PNG, atau PDF maksimal 2 MB.</p>
                            @error('proof_path') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-6">
                            <label for="notes" class="block font-medium text-sm text-gray-700">Catatan</label>
                            <textarea name="notes" id="notes" rows="4" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" placeholder="Contoh: pembayaran bulan Oktober">{{ old('notes') }}</textarea>
                            @error('notes') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        @error('payment') <p class="text-red-600 text-sm mb-4">{{ $message }}</p> @enderror

                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">
                                Kirim Pengajuan
                            </button>
                            <a href="{{ route('customer.payments.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md">
                                Batal
                            </a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>