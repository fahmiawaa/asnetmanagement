<x-app-layout>
    <div class="customer-list-page">
        <div class="reference-page-heading">
            <div>
                <h1>Daftar Pelanggan</h1>
            </div>
        </div>

        <div class="reference-content-card customer-list-card">
            <form method="GET" action="{{ route('customers.index') }}" class="customer-list-filters">
                <label class="customer-search-field">
                    <img src="{{ asset('images/icons/search.svg') }}" alt="">
                    <input type="search" name="q" value="{{ $search }}" placeholder="Cari pelanggan..." aria-label="Cari pelanggan">
                </label>
                <select name="status" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="package_id" aria-label="Filter paket">
                    <option value="">Semua Paket</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected((string) $packageId === (string) $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="customer-filter-button">Filter</button>
                <a href="{{ route('customers.create') }}" class="reference-primary-action"><span aria-hidden="true">+</span> Pelanggan Baru</a>
            </form>

            <div class="reference-table-panel customer-table-panel">
                <div class="overflow-x-auto">
                    <table class="customer-reference-table">
                        <thead>
                            <tr>
                                <th>Kode Pelanggan</th>
                                <th>Nama</th>
                                <th>Paket</th>
                                <th>WhatsApp</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customers as $customer)
                                <tr>
                                    <td>{{ $customer->customer_code }}</td>
                                    <td class="customer-name-cell">{{ $customer->name }}</td>
                                    <td>{{ $customer->internetPackage->name ?? '-' }}</td>
                                    <td>{{ $customer->whatsapp_number ?? '-' }}</td>
                                    <td>{{ $customer->due_day }} {{ now()->translatedFormat('M Y') }}</td>
                                    <td><span class="status-badge status-{{ $customer->status }}">{{ $statuses[$customer->status] ?? $customer->status }}</span></td>
                                    <td>
                                        <div class="customer-table-actions">
                                            <a href="{{ route('customers.show', $customer) }}" class="table-view-button"><img src="{{ asset('images/icons/eye.svg') }}" alt=""> Detail</a>
                                            <a href="{{ route('customers.edit', $customer) }}" class="table-edit-button"><img src="{{ asset('images/icons/edit.svg') }}" alt=""> Ubah</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-table">Belum ada data pelanggan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="customer-table-footer">
                    <span>Menampilkan {{ $customers->firstItem() ?? 0 }}-{{ $customers->lastItem() ?? 0 }} dari {{ $customers->total() }} data</span>
                    <div>{{ $customers->onEachSide(1)->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
