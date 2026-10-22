<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit User') }}</h2></x-slot>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-2xl font-bold mb-6">Edit User</h3>
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf
                    @method('PUT')
                    @include('users._form', ['creating' => false])
                    <div class="flex gap-3 mt-6">
                        <button class="bg-blue-600 text-white px-4 py-2 rounded-md">Simpan Perubahan</button>
                        <a href="{{ route('users.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>