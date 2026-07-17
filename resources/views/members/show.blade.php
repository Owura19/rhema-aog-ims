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
        <a href="{{ route('member-ledger.show', $member) }}" class="btn-outline" style="margin-right:8px;">
    <i class="fas fa-hand-holding-heart"></i> Giving Statement
</a>
        <a href="{{ route('members.edit', $member) }}" class="btn-primary">
            <i class="fas fa-edit"></i> Edit Member
        </a>
        <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm('Are you sure you want to remove this member?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 2fr; gap:20px;">

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
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
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
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
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
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
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
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
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
        {{-- ============================================================
     FAMILY RELATIONSHIPS CARD
     Paste this in resources/views/members/show.blade.php,
     right AFTER the closing </div> of the existing
     "Family Information" card (in the right column).
     ============================================================ --}}

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-people-roof" style="color:#7c3aed; margin-right:8px;"></i>Family Relationships</div>
    </div>
    <div style="padding:0;">
        @forelse($member->relationships as $rel)
            @if($rel->relatedMember)
            <div style="display:flex; align-items:center; gap:10px; padding:12px 20px; border-bottom:1px solid #f1f5f9;">
                <div class="member-avatar-placeholder" style="width:34px; height:34px; font-size:13px;">
                    {{ strtoupper(substr($rel->relatedMember->first_name, 0, 1)) }}
                </div>
                <div style="flex:1;">
                    <a href="{{ route('members.show', $rel->relatedMember) }}" style="font-size:14px; font-weight:600; color:#1e293b; text-decoration:none;">
                        {{ $rel->relatedMember->first_name }} {{ $rel->relatedMember->last_name }}
                    </a>
                    <div style="font-size:12px; color:#7c3aed;">{{ $rel->type_label }}</div>
                </div>
                @can('edit members')
                <form method="POST" action="{{ route('members.relationships.destroy', [$member, $rel->relatedMember]) }}"
                      onsubmit="return confirm('Remove this relationship?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:none; border:none; color:#dc2626; cursor:pointer;" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </form>
                @endcan
            </div>
            @endif
        @empty
            <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">No family relationships linked yet.</div>
        @endforelse
    </div>

    @can('edit members')
    <div class="card-body" style="border-top:1px solid #f1f5f9;">
        <form method="POST" action="{{ route('members.relationships.store', $member) }}">
            @csrf
            <div style="margin-bottom:10px;">
                <label class="form-label">Relationship</label>
                <select name="type" class="form-control" required>
                    <option value="spouse">Spouse</option>
                    <option value="parent">Parent</option>
                    <option value="child">Child</option>
                    <option value="sibling">Sibling</option>
                    <option value="guardian">Guardian</option>
                    <option value="other">Other</option>
                </select>
                <div style="font-size:11px; color:#94a3b8; margin-top:4px;">
                    e.g. "Parent" means the person you pick is this member's parent.
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label class="form-label">Member</label>
                <select name="related_member_id" class="form-control" required>
                    <option value="">— Select member —</option>
                    @foreach($otherMembers as $other)
                    <option value="{{ $other->id }}">{{ $other->first_name }} {{ $other->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary btn-sm" style="width:100%; justify-content:center;">
                <i class="fas fa-link"></i> Add Relationship
            </button>
        </form>
    </div>
    @endcan
</div>

{{-- ============================================================
     MEMBER PORTAL LOGIN CARD
     Paste into resources/views/members/show.blade.php,
     in the right column (e.g. after the Family Relationships card).
     ============================================================ --}}

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-right-to-bracket" style="color:#2563eb; margin-right:8px;"></i>Portal Login</div>
    </div>
    <div class="card-body">

        {{-- Show the temp password once, right after creation/reset --}}
        @if(session('portal_created'))
        <div class="alert alert-success" style="flex-direction:column; align-items:flex-start; gap:6px;">
            <div style="font-weight:700;"><i class="fas fa-circle-check"></i> Login ready — share these with the member:</div>
            <div style="font-size:13px;">Email: <strong>{{ session('portal_created')['email'] }}</strong></div>
            <div style="font-size:13px;">Temporary password: <strong style="font-family:monospace; background:#fff; padding:2px 8px; border-radius:5px;">{{ session('portal_created')['password'] }}</strong></div>
            <div style="font-size:12px; color:#15803d;">This password is shown only once. The member should change it after logging in.</div>
        </div>
        @endif

        @if($member->user)
            {{-- Member HAS a login --}}
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                <div style="width:38px; height:38px; border-radius:10px; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <div style="font-weight:600; color:#1e293b; font-size:14px;">Portal access active</div>
                    <div style="font-size:12px; color:#94a3b8;">{{ $member->user->email }}</div>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <form method="POST" action="{{ route('members.portal.reset', $member) }}" onsubmit="return confirm('Generate a new temporary password for this member?');">
                    @csrf
                    <button type="submit" class="btn-outline btn-sm"><i class="fas fa-key"></i> Reset Password</button>
                </form>
                <form method="POST" action="{{ route('members.portal.revoke', $member) }}" onsubmit="return confirm('Revoke this member''s portal login? They will no longer be able to sign in.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-outline btn-sm" style="border-color:#fecaca; color:#dc2626;"><i class="fas fa-ban"></i> Revoke</button>
                </form>
            </div>
        @else
            {{-- Member has NO login --}}
            @if(empty($member->email))
                <div style="font-size:13px; color:#94a3b8; margin-bottom:12px;">
                    <i class="fas fa-circle-info"></i> Add an email address to this member before creating a portal login.
                </div>
            @else
                <div style="font-size:13px; color:#64748b; margin-bottom:14px;">
                    Create a login so this member can sign in to view their own giving, pledges, and attendance.
                </div>
                <form method="POST" action="{{ route('members.portal.create', $member) }}" onsubmit="return confirm('Create a portal login for {{ $member->full_name }}?');">
                    @csrf
                    <button type="submit" class="btn-primary btn-sm"><i class="fas fa-user-plus"></i> Create Portal Login</button>
                </form>
            @endif
        @endif

    </div>
</div>

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