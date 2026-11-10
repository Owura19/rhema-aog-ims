{{-- Status badge. $state: live | approved | draft | missing --}}
@php
    [$class, $label] = match ($state) {
        'live'     => ['badge-success', 'Live'],
        'approved' => ['badge-info', 'Approved'],
        'draft'    => ['badge-warning', 'Draft'],
        default    => ['badge-gray', 'Missing'],
    };
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
