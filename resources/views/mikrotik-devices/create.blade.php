<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Perangkat MikroTik') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <form
                        method="POST"
                        action="{{ route('mikrotik-devices.store') }}"
                    >
                        @csrf

                        <div class="mb-4">
                            <label for="name" class="block font-medium text-sm text-gray-700">
                                Nama Perangkat
                            </label>

                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="{{ old('name') }}"
                                placeholder="Contoh: MikroTik Utama"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="host" class="block font-medium text-sm text-gray-700">
                                IP Address atau Hostname
                            </label>

                            <input
                                type="text"
                                name="host"
                                id="host"
                                value="{{ old('host') }}"
                                placeholder="Contoh: 192.168.88.1"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('host')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="api_port" class="block font-medium text-sm text-gray-700">
                                Port API
                            </label>

                            <input
                                type="number"
                                name="api_port"
                                id="api_port"
                                value="{{ old('api_port', 8728) }}"
                                min="1"
                                max="65535"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            <p class="text-sm text-gray-500 mt-1">
                                Port API biasa: 8728. Port API-SSL: 8729.
                            </p>

                            @error('api_port')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input
                                    type="checkbox"
                                    name="use_ssl"
                                    value="1"
                                    @checked(old('use_ssl'))
                                    class="rounded border-gray-300 text-blue-600 shadow-sm"
                                >

                                <span class="ml-2 text-sm text-gray-700">
                                    Gunakan API-SSL
                                </span>
                            </label>
                        </div>

                        <div class="mb-4">
                            <label for="username" class="block font-medium text-sm text-gray-700">
                                Username API MikroTik
                            </label>

                            <input
                                type="text"
                                name="username"
                                id="username"
                                value="{{ old('username') }}"
                                placeholder="Contoh: asnet_readonly"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            <p class="text-sm text-gray-500 mt-1">
                                Gunakan akun API khusus dengan akses read-only.
                            </p>

                            @error('username')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="block font-medium text-sm text-gray-700">
                                Password API MikroTik
                            </label>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            <p class="text-sm text-gray-500 mt-1">
                                Password akan dienkripsi sebelum disimpan.
                            </p>

                            @error('password')
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
                                placeholder="Catatan perangkat MikroTik"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >{{ old('notes') }}</textarea>

                            @error('notes')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <button
                                type="submit"
                                style="background-color: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer;"
                            >
                                Simpan Perangkat
                            </button>

                            <a
                                href="{{ route('mikrotik-devices.index') }}"
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