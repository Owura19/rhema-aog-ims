@extends('layouts.app')

@section('title', $member->full_name)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('members.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Members
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $member->full_name }}</h2>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="{{ route('members.edit', $member) }}" class="btn-primary">
            <i class="fas fa-edit"></i> Edit Member
        </a>
        <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm('Are you sure you want to remove this member?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div class="grid-main-rev">

    <!-- Left Column -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Profile Card -->
        <div class="card">
            <div class="card-body" style="text-align:center; padding:32px 24px;">
                @if($member->photo)
                    <img src="{{ asset('storage/'.$member->photo) }}" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:4px solid #e2e8f0; margin-bottom:16px;">
                @else
                    <div style="width:100px; height:100px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; color:#fff; font-size:36px; font-weight:700; margin:0 auto 16px;">
                        {{ strtoupper(substr($member->first_name,0,1)) }}
                    </div>
                @endif

                <div style="font-size:20px; font-weight:700; color:#1e293b;">{{ $member->full_name }}</div>
                <div style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:4px 12px; border-radius:20px; display:inline-block; margin:8px 0;">{{ $member->member_id }}</div>

                <div style="margin-top:12px;">
                    @if($member->membership_status === 'Active')
                        <span class="badge badge-success" style="font-size:13px; padding:5px 14px;">Active</span>
                    @elseif($member->membership_status === 'Visitor')
                        <span class="badge badge-warning" style="font-size:13px; padding:5px 14px;">Visitor</span>
                    @elseif($member->membership_status === 'Inactive')
                        <span class="badge badge-danger" style="font-size:13px; padding:5px 14px;">Inactive</span>
                    @else
                        <span class="badge badge-gray" style="font-size:13px; padding:5px 14px;">{{ $member->membership_status }}</span>
                    @endif
                </div>

                <div style="margin-top:16px; font-size:13px; color:#64748b;">{{ $member->member_type }}</div>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-info-circle" style="color:#2563eb; margin-right:8px;"></i>Quick Info</div>
            </div>
            <div class="card-body" style="padding:0;">
                <table style="width:100%;">
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Gender</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b; font-weight:500;">{{ $member->gender }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Age</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b; font-weight:500;">{{ $member->age ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Marital Status</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b; font-weight:500;">{{ $member->marital_status ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Date Joined</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b; font-weight:500;">{{ $member->date_joined?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Date Baptized</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b; font-weight:500;">{{ $member->date_baptized?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Fingerprint ID</td>
                        <td style="padding:12px 20px; font-size:14px; color:#1e293b; font-weight:500;">{{ $member->fingerprint_id ?? 'Not enrolled' }}</td>
                    </tr>
                </table>
            </div>
        </div>

    </div>

    <!-- Right Column -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Contact Information -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-phone" style="color:#16a34a; margin-right:8px;"></i>Contact Information</div>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Phone</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->phone ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Alt. Phone</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->alt_phone ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Email</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->email ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Digital Address</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->digital_address ?? '—' }}</div>
                    </div>
                    <div style="grid-column:span 2;">
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Residential Address</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->residential_address ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Work Information -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-briefcase" style="color:#7c3aed; margin-right:8px;"></i>Work Information</div>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Occupation</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->occupation ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Employer</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->employer ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-heartbeat" style="color:#dc2626; margin-right:8px;"></i>Emergency Contact</div>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Name</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->emergency_contact_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Phone</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->emergency_contact_phone ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Family -->
        @if($member->family)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-home" style="color:#e8a020; margin-right:8px;"></i>Family</div>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Family Name</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->family->family_name }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-bottom:4px;">Role in Family</div>
                        <div style="font-size:15px; color:#1e293b; font-weight:500;">{{ $member->family_role ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Notes -->
        @if($member->notes)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-sticky-note" style="color:#e8a020; margin-right:8px;"></i>Notes</div>
            </div>
            <div class="card-body">
                <p style="font-size:14px; color:#374151; line-height:1.8; margin:0;">{{ $member->notes }}</p>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection