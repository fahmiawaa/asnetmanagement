<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Paket Internet') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <form
                        method="POST"
                        action="{{ route('internet-packages.update', $internetPackage) }}"
                    >
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="package_code" class="block font-medium text-sm text-gray-700">
                                Kode Paket
                            </label>

                            <input
                                type="text"
                                name="package_code"
                                id="package_code"
                                value="{{ old('package_code', $internetPackage->package_code) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('package_code')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="name" class="block font-medium text-sm text-gray-700">
                                Nama Paket
                            </label>

                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="{{ old('name', $internetPackage->name) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="download_speed" class="block font-medium text-sm text-gray-700">
                                Kecepatan Download
                            </label>

                            <input
                                type="text"
                                name="download_speed"
                                id="download_speed"
                                value="{{ old('download_speed', $internetPackage->download_speed) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('download_speed')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="upload_speed" class="block font-medium text-sm text-gray-700">
                                Kecepatan Upload
                            </label>

                            <input
                                type="text"
                                name="upload_speed"
                                id="upload_speed"
                                value="{{ old('upload_speed', $internetPackage->upload_speed) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('upload_speed')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="price" class="block font-medium text-sm text-gray-700">
                                Harga Bulanan
                            </label>

                            <input
                                type="number"
                                name="price"
                                id="price"
                                value="{{ old('price', $internetPackage->price) }}"
                                min="0"
                                step="0.01"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >

                            @error('price')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="mikrotik_profile" class="block font-medium text-sm text-gray-700">
                                Profile MikroTik
                            </label>

                            <input
                                type="text"
                                name="mikrotik_profile"
                                id="mikrotik_profile"
                                value="{{ old('mikrotik_profile', $internetPackage->mikrotik_profile) }}"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >

                            @error('mikrotik_profile')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="status" class="block font-medium text-sm text-gray-700">
                                Status Paket
                            </label>

                            <select
                                name="status"
                                id="status"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                <option value="active" @selected(old('status', $internetPackage->status) === 'active')>
                                    Aktif
                                </option>

                                <option value="inactive" @selected(old('status', $internetPackage->status) === 'inactive')>
                                    Tidak Aktif
                                </option>
                            </select>

                            @error('status')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block font-medium text-sm text-gray-700">
                                Deskripsi
                            </label>

                            <textarea
                                name="description"
                                id="description"
                                rows="3"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                            >{{ old('description', $internetPackage->description) }}</textarea>

                            @error('description')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
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
                                href="{{ route('internet-packages.index') }}"
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