<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Akun PPPoE') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="mb-6">
                    <p class="text-sm text-gray-500">
                        Perangkat: {{ $pppoeAccount->mikrotikDevice->name ?? '-' }}
                    </p>
                    <h3 class="text-2xl font-bold mt-1">
                        {{ $pppoeAccount->username }}
                    </h3>
                    <p class="text-gray-600 mt-2">
                        Perubahan akan dikirim ke MikroTik jika
                        <code>MIKROTIK_ALLOW_REAL_ACTIONS=true</code>.
                    </p>
                </div>

                <form method="POST" action="{{ route('pppoe-accounts.update', $pppoeAccount) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="username" class="block font-medium text-sm text-gray-700">
                                Username PPPoE
                            </label>
                            <input
                                type="text"
                                name="username"
                                id="username"
                                value="{{ old('username', $pppoeAccount->username) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                            @error('username') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="password" class="block font-medium text-sm text-gray-700">
                                Password PPPoE
                            </label>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                autocomplete="new-password"
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                Kosongkan jika password tidak diubah.
                            </p>
                            @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="service" class="block font-medium text-sm text-gray-700">
                                Service
                            </label>
                            <input
                                type="text"
                                name="service"
                                id="service"
                                value="{{ old('service', $pppoeAccount->service ?: 'pppoe') }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                            @error('service') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="profile" class="block font-medium text-sm text-gray-700">
                                Profile MikroTik
                            </label>
                            <input
                                type="text"
                                name="profile"
                                id="profile"
                                value="{{ old('profile', $pppoeAccount->profile) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >
                            @error('profile') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="local_address" class="block font-medium text-sm text-gray-700">
                                Local Address
                            </label>
                            <input
                                type="text"
                                name="local_address"
                                id="local_address"
                                value="{{ old('local_address', $pppoeAccount->local_address) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >
                            @error('local_address') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="remote_address" class="block font-medium text-sm text-gray-700">
                                Remote Address
                            </label>
                            <input
                                type="text"
                                name="remote_address"
                                id="remote_address"
                                value="{{ old('remote_address', $pppoeAccount->remote_address) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >
                            @error('remote_address') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="comment" class="block font-medium text-sm text-gray-700">
                            Catatan
                        </label>
                        <textarea
                            name="comment"
                            id="comment"
                            rows="4"
                            class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                        >{{ old('comment', $pppoeAccount->comment) }}</textarea>
                        @error('comment') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap gap-3 mt-6">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">
                            Simpan dan Sinkronkan
                        </button>
                        <a href="{{ route('pppoe-accounts.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>