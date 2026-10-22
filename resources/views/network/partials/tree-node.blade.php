<li>
    <div class="topo-tree-row {{ $node['is_active'] ? '' : 'is-inactive' }}">
        <button type="button" class="topo-tree-node" data-node-id="{{ $node['id'] }}">
            <span class="topo-type topo-type-{{ $node['type'] }}">{{ strtoupper(str_replace('_', ' ', $node['type'])) }}</span>
            <span class="topo-tree-name">{{ $node['name'] }}@if ($node['code']) <small>({{ $node['code'] }})</small>@endif</span>
            @if ($node['ont'])
                <span class="topo-dot topo-q-{{ $node['ont']['quality'] }}" title="Kualitas RX: {{ $node['ont']['quality'] }}"></span>
                <span class="status-badge ont-status-{{ $node['ont']['status'] }}">{{ ucfirst($node['ont']['status']) }}</span>
            @elseif ($node['summary']['ont_total'] > 0)
                <small class="topo-count">{{ $node['summary']['online'] }}/{{ $node['summary']['ont_total'] }} ONT online</small>
            @endif
        </button>
    </div>
    @if (! empty($node['children']))
        <ul>
            @foreach ($node['children'] as $child)
                @include('network.partials.tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
