@extends('layouts.app')

@section('title', $visitor->full_name)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('visitors.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Visitors
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $visitor->full_name }}</h2>
        <div style="font-size:13px; color:#64748b;">{{ $visitor->visit_type }} · {{ $visitor->visit_date->format('M d, Y') }}</div>
    </div>
    <div style="display:flex; gap:10px;">
        @if(!$visitor->converted_to_member)
        <form method="POST" action="{{ route('visitors.convert', $visitor) }}" onsubmit="return confirm('Convert {{ $visitor->full_name }} to a full member?')">
            @csrf
            <button type="submit" class="btn-primary" style="background:#16a34a;">
                <i class="fas fa-user-plus"></i> Convert to Member
            </button>
        </form>
        @else
        <a href="{{ route('members.show', $visitor->member) }}" class="btn-primary" style="background:#16a34a;">
            <i class="fas fa-user"></i> View Member Profile
        </a>
        @endif
        <a href="{{ route('visitors.edit', $visitor) }}" class="btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
        <form method="POST" action="{{ route('visitors.destroy', $visitor) }}" onsubmit="return confirm('Delete this visitor?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div class="grid-2">

    <!-- Personal Info -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-user" style="color:#2563eb; margin-right:8px;"></i>Personal Information</div>
        </div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; width:40%;">Full Name</td>
                    <td style="padding:12px 20px; font-size:14px; font-weight:600; color:#1e293b;">{{ $visitor->full_name }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Gender</td>
                    <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->gender ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Phone</td>
                    <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->phone ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Email</td>
                    <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->email ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Date of Birth</td>
                    <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->date_of_birth?->format('M d, Y') ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Marital Status</td>
                    <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->marital_status ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Occupation</td>
                    <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->occupation ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Address</td>
                    <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->address ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Right Column -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Visit Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-church" style="color:#7c3aed; margin-right:8px;"></i>Visit Information</div>
            </div>
            <div class="card-body" style="padding:0;">
                <table style="width:100%;">
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; width:45%;">Visit Date</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->visit_date->format('l, F d, Y') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Visit Type</td>
                        <td style="padding:12px 20px;">
                            @if($visitor->visit_type === 'First Time')
                                <span class="badge badge-info">First Time</span>
                            @elseif($visitor->visit_type === 'Second Time')
                                <span class="badge badge-warning">Second Time</span>
                            @elseif($visitor->visit_type === 'Third Time')
                                <span class="badge" style="background:#f3e8ff; color:#7c3aed;">Third Time</span>
                            @else
                                <span class="badge badge-success">Regular</span>
                            @endif
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">How Heard</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->how_heard ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Service</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->churchService?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Recorded By</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->recordedBy?->name ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Follow-up Status -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-phone" style="color:#e8a020; margin-right:8px;"></i>Follow-up Status</div>
            </div>
            <div class="card-body" style="padding:0;">
                <table style="width:100%;">
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; width:45%;">Status</td>
                        <td style="padding:12px 20px;">
                            @php $color = $visitor->follow_up_status_color; @endphp
                            <span class="badge badge-{{ $color }}">{{ $visitor->follow_up_status }}</span>
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Follow-up Date</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->follow_up_date?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Followed Up By</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->followedUpBy?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Notes</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b;">{{ $visitor->follow_up_notes ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Member Status -->
        @if($visitor->converted_to_member)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-check" style="color:#16a34a; margin-right:8px;"></i>Member Status</div>
            </div>
            <div class="card-body">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="member-avatar-placeholder" style="width:48px; height:48px; font-size:18px; background:#16a34a;">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <div style="font-size:15px; font-weight:700; color:#16a34a;">Converted to Member</div>
                        <div style="font-size:13px; color:#64748b;">{{ $visitor->member?->member_id }}</div>
                    </div>
                </div>
                @if($visitor->member)
                <a href="{{ route('members.show', $visitor->member) }}" class="btn-primary btn-sm" style="margin-top:12px; background:#16a34a;">
                    <i class="fas fa-user"></i> View Member Profile
                </a>
                @endif
            </div>
        </div>
        @endif

        <!-- Notes -->
        @if($visitor->notes)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-sticky-note" style="color:#e8a020; margin-right:8px;"></i>Notes</div>
            </div>
            <div class="card-body">
                <p style="font-size:14px; color:#374151; line-height:1.8; margin:0;">{{ $visitor->notes }}</p>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection