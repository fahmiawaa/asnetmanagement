<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="name" class="block font-medium text-sm text-gray-700">Nama</label>
        <input name="name" id="name" value="{{ old('name', $user->name ?? '') }}" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email', $user->email ?? '') }}" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
        @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="role" class="block font-medium text-sm text-gray-700">Role</label>
        <select name="role" id="role" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
            @foreach (['owner' => 'Owner', 'admin' => 'Admin', 'teknisi' => 'Teknisi', 'pelanggan' => 'Pelanggan'] as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role ?? 'admin') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="customer_id" class="block font-medium text-sm text-gray-700">Data pelanggan</label>
        <select name="customer_id" id="customer_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
            <option value="">Tidak terhubung</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected(old('customer_id', $user->customer_id ?? '') == $customer->id)>
                    {{ $customer->customer_code }} — {{ $customer->name }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Wajib dipilih jika role adalah pelanggan.</p>
        @error('customer_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="block font-medium text-sm text-gray-700">Password {{ $creating ? '' : 'baru (opsional)' }}</label>
        <input type="password" name="password" id="password" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" {{ $creating ? 'required' : '' }} autocomplete="new-password">
        <p class="text-xs text-gray-500 mt-1">Minimal 12 karakter.</p>
        @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Konfirmasi password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" {{ $creating ? 'required' : '' }} autocomplete="new-password">
    </div>
</div>

@unless ($creating)
    <input type="hidden" name="active" value="0">
    <label class="flex items-center gap-2 mt-5">
        <input type="checkbox" name="active" value="1" @checked(old('active', $user->active)) class="rounded border-gray-300">
        <span class="text-sm text-gray-700">User aktif</span>
    </label>
@endunless