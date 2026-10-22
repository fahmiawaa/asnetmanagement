<x-app-layout>
    <div class="module-page audit-log-page">
        <div class="module-page-heading"><h1>Audit Log</h1><span class="production-safe-badge">✓ Operasi Produksi - Aman</span></div>
        <div class="module-table-card audit-table-card">
            <form method="GET" action="{{ route('audit-logs.index') }}" class="audit-filter-row">
                <label><span>Pencarian</span><div class="module-search-field"><img src="{{ asset('images/icons/search.svg') }}" alt=""><input type="search" name="q" value="{{ $search }}" placeholder="Pencarian"></div></label>
                <label><span>Aksi</span><select name="action"><option value="">Semua Aksi</option>@foreach ($actions as $auditAction)<option value="{{ $auditAction }}" @selected($action === $auditAction)>{{ $auditAction }}</option>@endforeach</select></label>
                <label><span>Rentang Tanggal</span><div class="module-date-field"><input type="date" name="date_from" value="{{ $dateFrom }}"><span>–</span><input type="date" name="date_to" value="{{ $dateTo }}"></div></label>
                <button type="submit" class="module-filter-button">Terapkan Filter</button>
            </form>
            <div class="module-table-scroll"><table class="module-reference-table audit-reference-table"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Deskripsi</th><th>Modul</th><th>Alamat IP</th><th>Detail Metadata</th></tr></thead><tbody>
                @forelse ($logs as $log)
                    <tr><td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td><td>{{ $log->user->name ?? 'System' }}</td><td>{{ $log->action }}</td><td>{{ $log->description }}</td><td>{{ ucfirst(strtok($log->action, '.')) }}</td><td>{{ $log->ip_address ?? '-' }}</td><td>@if ($log->metadata)<details><summary>◉ Lihat Detail</summary><pre>{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@else<span>◉ Lihat Detail</span>@endif</td></tr>
                @empty
                    <tr><td colspan="7" class="empty-table">Belum ada aktivitas yang tercatat.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="module-table-footer"><span>Menampilkan {{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }} dari {{ $logs->total() }} entri</span>{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
