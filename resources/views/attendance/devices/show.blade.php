@extends('layouts.app')

@section('title', $device->name)

@php
    $statusLabel = [
        'matched'           => ['On attendance', 'badge-success'],
        'unmatched'         => ['Unmatched ID', 'badge-danger'],
        'no_service'        => ['No service that day', 'badge-warning'],
        'duplicate_ignored' => ['Later punch, ignored', 'badge-gray'],
        'kept_manual'       => ['Manual mark kept', 'badge-info'],
    ];
    $waiting = ($counts['unmatched'] ?? 0) + ($counts['no_service'] ?? 0);
@endphp

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <a href="{{ route('devices.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Devices</a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $device->name }}
            <span class="badge {{ $device->is_active ? 'badge-success' : 'badge-gray' }}" style="font-size:12px; vertical-align:middle;">{{ $device->status_label }}</span>
        </h2>
    </div>
    <div class="toolbar">
        @if($device->has_ip)
            <form method="POST" action="{{ route('devices.test', $device) }}">@csrf
                <button type="submit" class="btn-outline btn-sm"><i class="fas fa-plug"></i> Test connection</button>
            </form>
        @endif
        <a href="{{ route('devices.edit', $device) }}" class="btn-primary btn-sm toolbar-main"><i class="fas fa-edit"></i> Edit</a>
    </div>
</div>

@if($newKey)
<div class="card" id="new-key" style="margin-bottom:20px; border:2px solid #16a34a;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-key" style="color:#16a34a; margin-right:8px;"></i>{{ $replaced ? 'New connection key' : 'Connection key' }}</div>
    </div>
    <div class="card-body">
        <div class="alert alert-danger" style="margin-bottom:14px;">
            <i class="fas fa-triangle-exclamation"></i>
            <strong>Copy this key now. It cannot be shown again.</strong>
            Only a scrambled copy is kept here. Don't refresh this page (that would make yet another key). If you lose it, generate a new one.
            @if($replaced) The previous key has stopped working. @endif
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <input type="text" id="key-value" value="{{ $newKey }}" readonly class="form-control" style="flex:1; min-width:260px; font-family:monospace;" onfocus="this.select()" aria-label="Connection key">
            <button type="button" class="btn-primary" id="copy-key"><i class="fas fa-copy"></i> Copy</button>
        </div>
        <div id="copy-note" style="font-size:12px; color:#16a34a; margin-top:6px; min-height:16px;"></div>
    </div>
</div>
<script>
document.getElementById('copy-key').addEventListener('click', function () {
    const field = document.getElementById('key-value');
    const done = () => { document.getElementById('copy-note').textContent = 'Copied.'; };
    field.select();
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(field.value).then(done, () => { document.execCommand('copy'); done(); });
    } else {
        document.execCommand('copy'); done();
    }
});
</script>
@endif

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-user-check" style="color:#16a34a;"></i></div>
        <div><div class="stat-value">{{ $counts['matched'] ?? 0 }}</div><div class="stat-label">Matched</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;"><i class="fas fa-user-question" style="color:#dc2626;"></i></div>
        <div><div class="stat-value">{{ $counts['unmatched'] ?? 0 }}</div><div class="stat-label">Unmatched IDs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;"><i class="fas fa-calendar-xmark" style="color:#d97706;"></i></div>
        <div><div class="stat-value">{{ $counts['no_service'] ?? 0 }}</div><div class="stat-label">No service that day</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;"><i class="fas fa-fingerprint" style="color:#2563eb;"></i></div>
        <div><div class="stat-value">{{ $total }}</div><div class="stat-label">Punches received</div></div>
    </div>
</div>

<div class="grid-2" style="margin-bottom:20px;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-circle-info" style="color:#2563eb; margin-right:8px;"></i>Device</div></div>
        <div class="card-body" style="font-size:14px; line-height:1.9;">
            <div><strong>Serial number:</strong> {{ $device->serial_number ?: '—' }}</div>
            <div><strong>Location:</strong> {{ $device->location ?: '—' }}</div>
            <div><strong>Connection:</strong>
                @if($device->has_ip) directly, {{ $device->ip_address }}:{{ $device->port }} (last synced {{ $device->last_synced_label }})
                @else through the church bridge computer (no IP) @endif
            </div>
            <div><strong>Last seen:</strong> {{ $device->last_seen_at ? $device->last_seen_at->format('d M Y H:i') . ' (' . $device->last_seen_at->diffForHumans() . ')' : 'Never' }}</div>
            <div><strong>Last punch:</strong> {{ $device->last_punch_at?->format('d M Y H:i') ?? 'None yet' }}</div>
            @if($device->notes)<div><strong>Notes:</strong> {{ $device->notes }}</div>@endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-key" style="color:#16a34a; margin-right:8px;"></i>Connection key</div></div>
        <div class="card-body" style="font-size:14px;">
            <div style="margin-bottom:8px;">
                @if($device->has_key)
                    <span class="badge badge-success">Set</span> created {{ $device->token_created_at?->format('d M Y H:i') }}
                @else
                    <span class="badge badge-warning">Not set</span> the bridge cannot send punches yet
                @endif
            </div>
            <div style="font-size:13px; color:#64748b; margin-bottom:12px;">
                The bridge program on the church PC sends punches to <span style="font-family:monospace;">{{ $bridgeUrl }}</span> using this key.
                @unless($device->is_active) <strong style="color:#b45309;">The device is inactive: its key is refused until it is active.</strong> @endunless
            </div>
            <div class="toolbar" style="justify-content:flex-start;">
                <form method="POST" action="{{ route('devices.key.generate', $device) }}"
                      @if($device->has_key) onsubmit="return confirm('Replace the key? The current key stops working at once and the bridge must be given the new one.')" @endif>
                    @csrf
                    <button type="submit" class="btn-primary btn-sm"><i class="fas fa-key"></i> {{ $device->has_key ? 'Regenerate key' : 'Generate connection key' }}</button>
                </form>
                @if($device->has_key)
                <form method="POST" action="{{ route('devices.key.revoke', $device) }}" onsubmit="return confirm('Revoke the key? The bridge stops working until a new key is generated.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger btn-sm"><i class="fas fa-ban"></i> Revoke</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user-question" style="color:#dc2626; margin-right:8px;"></i>Unmatched IDs ({{ $unmatchedIdCount }})</div>
        @if($waiting > 0)
        <form method="POST" action="{{ route('devices.reprocess', $device) }}">@csrf
            <button type="submit" class="btn-outline btn-sm" title="Run waiting punches again, after giving members their ID or creating the service">
                <i class="fas fa-rotate"></i> Reprocess waiting punches ({{ $waiting }})
            </button>
        </form>
        @endif
    </div>
    @if($unmatchedIds->isEmpty())
        <div style="padding:20px 24px; color:#94a3b8; font-size:13px;">Every punch so far belongs to a member.@if($waiting > 0) {{ $counts['no_service'] ?? 0 }} are waiting for a service on their day.@endif</div>
    @else
    <div style="padding:12px 24px 0; font-size:13px; color:#64748b;">
        Give each member their "Fingerprint Device ID" (their user number on the device), then reprocess.
    </div>
    <div class="table-wrap">
        <table class="table table-cards">
            <thead><tr><th>Device user ID</th><th style="text-align:right;">Punches</th><th>Last punch</th></tr></thead>
            <tbody>
            @foreach($unmatchedIds as $row)
                <tr>
                    <td data-label="Device user ID" style="font-family:monospace; font-weight:600;">{{ $row->device_user_id }}</td>
                    <td data-label="Punches" style="text-align:right;">{{ $row->punches }}</td>
                    <td data-label="Last punch">{{ \Carbon\Carbon::parse($row->last_punch)->format('d M Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-clock-rotate-left" style="color:#64748b; margin-right:8px;"></i>Last 20 punches</div></div>
    @if($recent->isEmpty())
        <div style="padding:20px 24px; color:#94a3b8; font-size:13px;">No punches received yet.</div>
    @else
    <div class="table-wrap">
        <table class="table table-cards">
            <thead><tr><th>Punched</th><th>Who</th><th>Service</th><th>Result</th></tr></thead>
            <tbody>
            @foreach($recent as $p)
                @php([$label, $badge] = $statusLabel[$p->status] ?? [$p->status, 'badge-gray'])
                <tr>
                    <td data-label="Punched" style="white-space:nowrap;">{{ $p->punched_at->format('d M Y H:i:s') }}</td>
                    <td data-label="Who">{{ $p->member ? $p->member->full_name : 'Unmatched ID ' . $p->device_user_id }}</td>
                    <td data-label="Service">{{ $p->service ? $p->service->name . ' · ' . $p->service->service_date->format('d M') : '—' }}</td>
                    <td data-label="Result"><span class="badge {{ $badge }}">{{ $label }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
