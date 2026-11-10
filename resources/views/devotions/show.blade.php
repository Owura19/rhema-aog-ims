@extends('layouts.app')

@section('title', $devotion->title)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
        <a href="{{ route('devotions.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Devotions
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $devotion->title }}</h2>
        <div style="font-size:13px; color:#64748b;">{{ $devotion->publish_date->format('l j F Y') }} · @include('devotions._state', ['state' => $devotion->state()])</div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        @can('manage devotions')
            <a href="{{ route('devotions.edit', $devotion) }}" class="btn-outline"><i class="fas fa-edit"></i> Edit</a>
        @endcan
        @can('approve devotions')
            @if($devotion->isApproved())
                <form method="POST" action="{{ route('devotions.withdraw', $devotion) }}" onsubmit="return confirm(@js('Withdraw approval? The devotion will be hidden from the app until it is approved again.'))">
                    @csrf
                    <button type="submit" class="btn-outline"><i class="fas fa-rotate-left"></i> Withdraw approval</button>
                </form>
            @else
                <form method="POST" action="{{ route('devotions.approve', $devotion) }}">
                    @csrf
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Approve</button>
                </form>
            @endif
        @endcan
        @can('manage devotions')
            @if(! $devotion->isApproved() || auth()->user()->can('approve devotions'))
                <form method="POST" action="{{ route('devotions.destroy', $devotion) }}" onsubmit="return confirm(@js('Delete the devotion for ' . $devotion->publish_date->format('j M Y') . '?'))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
            @endif
        @endcan
    </div>
</div>

<div class="grid-main">

    {{-- As members read it. Text is escaped with e() first; nl2br then only adds line breaks. --}}
    <div class="card">
        <div class="card-body" style="max-width:720px;">
            <div style="font-size:13px; font-weight:700; color:#2563eb; text-transform:uppercase; letter-spacing:.04em;">{{ $devotion->scripture_reference }}</div>
            @if($devotion->verse_text)
                <blockquote style="margin:10px 0 18px; padding:12px 16px; border-left:4px solid #2563eb; background:#f8fafc; font-style:italic; color:#374151;">{{ $devotion->verse_text }}</blockquote>
            @else
                <div style="margin:8px 0 18px; font-size:13px; color:#94a3b8;"><i class="fas fa-circle-info"></i> The KJV verse fills in automatically once the reference is checked (coming soon).</div>
            @endif

            <div data-field="body" style="font-size:15px; line-height:1.8; color:#1e293b;">{!! nl2br(e($devotion->body)) !!}</div>

            <h3 style="font-size:15px; font-weight:700; margin:22px 0 6px;">Prayer</h3>
            <div data-field="prayer" style="font-size:15px; line-height:1.8; color:#1e293b;">{!! nl2br(e($devotion->prayer)) !!}</div>

            <div style="margin-top:22px; padding:12px 16px; background:#eff6ff; border-radius:10px; font-weight:600; color:#1e3a8a;">
                <i class="fas fa-lightbulb"></i> {{ $devotion->thought_for_the_day }}
            </div>

            @if($devotion->author_name)
                <div style="margin-top:16px; font-size:13px; color:#64748b;">— {{ $devotion->author_name }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-info-circle" style="color:#7c3aed; margin-right:8px;"></i>Details</div>
        </div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%; font-size:13px;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Status</td>
                    <td style="padding:10px 20px;">@include('devotions._state', ['state' => $devotion->state()])</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Approved</td>
                    <td style="padding:10px 20px;">
                        @if($devotion->isApproved())
                            {{ $devotion->approvedBy?->name ?? 'Unknown' }}, {{ $devotion->approved_at?->format('j M Y, g:i A') }}
                        @else
                            Not yet
                        @endif
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Source</td>
                    <td style="padding:10px 20px;">
                        {{ $devotion->source === \App\Models\Devotion::SOURCE_AI ? 'AI draft' : 'Written by staff' }}
                        @if($devotion->source === \App\Models\Devotion::SOURCE_AI && $devotion->ai_edited)
                            (edited by staff)
                        @endif
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Theme</td>
                    <td style="padding:10px 20px;">
                        @if($devotion->no_theme)
                            <span class="badge badge-danger">no theme</span>
                        @else
                            {{ $devotion->theme?->title ?? '—' }}
                        @endif
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Created</td>
                    <td style="padding:10px 20px;">{{ $devotion->createdBy?->name ?? '—' }}, {{ $devotion->created_at?->format('j M Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Last edited</td>
                    <td style="padding:10px 20px;">{{ $devotion->updatedBy?->name ?? '—' }}, {{ $devotion->updated_at?->format('j M Y') }}</td>
                </tr>
            </table>
        </div>
    </div>

</div>

@endsection
