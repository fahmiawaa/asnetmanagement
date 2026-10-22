<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Pembayaran Saya') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
                    <div>
                        <p class="text-sm text-gray-500">Pembayaran untuk invoice</p>
                        <h3 class="text-2xl font-bold text-gray-800">{{ $payment->invoice->invoice_number ?? '-' }}</h3>
                    </div>
                    <a href="{{ route('customer.payments.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md text-center">
                        Kembali
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3">Informasi pembayaran</h4>
                        <div class="space-y-2 text-gray-700">
                            <p><strong>Tanggal:</strong> {{ optional($payment->payment_date)->format('d-m-Y') ?? '-' }}</p>
                            <p><strong>Nominal:</strong> Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</p>
                            <p><strong>Metode:</strong> {{ match ($payment->method) {
                                'bank_transfer' => 'Transfer Bank',
                                'e_wallet' => 'E-Wallet',
                                'qris' => 'QRIS',
                                default => ucfirst($payment->method),
                            } }}</p>
                            <p><strong>Status:</strong> {{ ucfirst($payment->status) }}</p>
                            <p><strong>Diproses:</strong> {{ optional($payment->verified_at)->format('d-m-Y H:i') ?? 'Menunggu verifikasi pengelola' }}</p>
                        </div>
                    </div>

                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3">Informasi invoice</h4>
                        <div class="space-y-2 text-gray-700">
                            <p><strong>Nomor:</strong> {{ $payment->invoice->invoice_number ?? '-' }}</p>
                            <p><strong>Jatuh tempo:</strong> {{ optional($payment->invoice->due_date)->format('d-m-Y') ?? '-' }}</p>
                            <p><strong>Status invoice:</strong> {{ ucfirst($payment->invoice->status ?? '-') }}</p>
                            @if ($payment->proof_path)
                                <p><a href="{{ route('customer.payments.proof', $payment) }}" target="_blank" class="text-blue-600 hover:underline">Lihat bukti pembayaran</a></p>
                            @else
                                <p class="text-gray-500">Belum ada bukti pembayaran.</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($payment->method === 'qris')
                    @php
                        $qrisImagePath = config('payments.qris.image_path');
                        $qrisImageExists = is_string($qrisImagePath) && is_file(public_path($qrisImagePath));
                        $qrisExpired = $payment->qris_expires_at && now()->greaterThan($payment->qris_expires_at);
                    @endphp
                    <div class="border rounded-lg p-5 mb-8 text-center">
                        <h4 class="text-lg font-semibold text-gray-800 mb-2">
                            QRIS {{ $qrisExpired ? 'kedaluwarsa' : 'aktif' }}
                        </h4>
                        <p class="text-gray-600 mb-4">
                            Referensi: {{ $payment->qris_reference ?? '-' }} · aktif sampai {{ optional($payment->qris_expires_at)->format('d-m-Y H:i') ?? '-' }}
                        </p>
                        @if ($qrisImageExists && !$qrisExpired)
                            <img src="{{ asset($qrisImagePath) }}" alt="QRIS {{ config('payments.qris.merchant_name', 'ASNet') }}" style="display:block;width:260px;height:260px;object-fit:contain;margin:0 auto 14px;">
                            <p class="text-sm text-gray-500">Setelah membayar, unggah bukti atau pastikan bukti sudah dikirim untuk diverifikasi pengelola.</p>
                        @elseif ($qrisExpired)
                            <p class="text-red-600">Sesi QRIS ini sudah kedaluwarsa. Hubungi pengelola untuk membuat pengajuan baru.</p>
                        @else
                            <p class="text-red-600">Gambar QRIS merchant belum tersedia.</p>
                        @endif
                    </div>
                @endif

                @if ($payment->notes)
                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-2">Catatan</h4>
                        <p class="text-gray-700 whitespace-pre-line">{{ $payment->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>