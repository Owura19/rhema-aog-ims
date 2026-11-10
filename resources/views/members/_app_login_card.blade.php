{{--
    MOBILE APP CARD on the member page. Expects: $member, $appAccess (App\Support\MemberAppAccess).
    Status lines for everyone who can view the member; buttons only with 'edit members'
    (MemberAppLoginController's routes need it). The temporary password is flashed once.
--}}
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-mobile-screen" style="color:#2563eb; margin-right:8px;"></i>Mobile App</div>
    </div>
    <div class="card-body">

        {{-- Shown once, right after giving an app login or resetting its password --}}
        @if(session('app_login_created'))
        <div class="alert alert-success" style="flex-direction:column; align-items:flex-start; gap:6px;">
            <div style="font-weight:700;"><i class="fas fa-circle-check"></i> App login ready: share these with the member</div>
            <div style="font-size:13px;">Phone: <strong>{{ session('app_login_created')['phone'] }}</strong></div>
            <div style="font-size:13px;">Temporary password: <strong style="font-family:monospace; background:#fff; padding:2px 8px; border-radius:5px;">{{ session('app_login_created')['password'] }}</strong></div>
            <div style="font-size:12px; color:#15803d;">This password is shown only once. The app will ask the member to choose a new one.</div>
        </div>
        @endif

        @if($appAccess->hasAppLogin())
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                <div style="width:38px; height:38px; border-radius:10px; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-mobile-screen"></i>
                </div>
                <div>
                    <div style="font-weight:600; color:#1e293b; font-size:14px;">
                        App login: {{ $appAccess->maskedPhone }}
                        @if($appAccess->mustChangePassword())
                            <span class="badge badge-warning">must change password</span>
                        @endif
                    </div>
                    <div style="font-size:12px; color:#94a3b8;">Signed in on {{ $appAccess->devices }} {{ \Illuminate\Support\Str::plural('device', $appAccess->devices) }}</div>
                </div>
            </div>
            @can('edit members')
                @if($appAccess->refusal() === 'staff')
                    <div style="font-size:13px; color:#94a3b8;">This is a staff account. Staff app logins are not managed here.</div>
                @else
                <div style="display:flex; gap:8px;">
                    @if($appAccess->refusal() === null)
                    <form method="POST" action="{{ route('members.app-login.reset', $member) }}" onsubmit="return confirm(@js('Give this member a new temporary app password? They will be signed out of the app on every device.'));">
                        @csrf
                        <button type="submit" class="btn-outline btn-sm"><i class="fas fa-key"></i> Reset app password</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('members.app-login.destroy', $member) }}" onsubmit="return confirm(@js($appAccess->user->email ? 'Remove this member\'s app login? They will be signed out of the app on every device. Their website login keeps working.' : 'Remove this member\'s app login? They will be signed out of the app on every device.'));">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-outline btn-sm" style="border-color:#fecaca; color:#dc2626;"><i class="fas fa-ban"></i> Remove app login</button>
                    </form>
                </div>
                @endif
            @endcan
        @else
            <div style="font-size:13px; color:#64748b; margin-bottom:14px;">
                <i class="fas fa-circle-info"></i>
                @if($appAccess->hasLogin())
                    No app login. This member can't activate the app themselves because they already have a website login.
                @elseif($appAccess->state === \App\Support\MemberAppAccess::CAN_ACTIVATE)
                    No app login. This member can activate the mobile app themselves with the number on record ({{ $appAccess->phone }}).
                @elseif($appAccess->state === \App\Support\MemberAppAccess::NUMBER_TAKEN)
                    No app login. {{ $appAccess->phone }} already belongs to another app account, so this member can't activate the app with it.
                @elseif($appAccess->state === \App\Support\MemberAppAccess::NO_VALID_PHONE)
                    No app login. No valid phone number on record, so this member can't activate the mobile app.
                @else
                    No app login. Only Active or Inactive members can activate the mobile app (status: {{ $member->membership_status }}).
                @endif
            </div>

            @can('edit members')
                @if(! $appAccess->eligibleStatus)
                    {{-- the line above already says why --}}
                @elseif($appAccess->refusal() === 'staff')
                    <div style="font-size:13px; color:#94a3b8;">This member's login is a staff account. Staff app logins are not managed here.</div>
                @elseif($appAccess->refusal() === 'inactive')
                    <div style="font-size:13px; color:#94a3b8;">This member's login is deactivated, so it can't be given app access.</div>
                @else
                    @php
                        $giveConfirm = 'Give ' . $member->full_name . ' an app login? A temporary password will be shown once.';
                        if ($appAccess->user?->email) {
                            $giveConfirm .= ' This also replaces their website password and signs them out everywhere.';
                        }
                    @endphp
                    <form method="POST" action="{{ route('members.app-login.store', $member) }}" onsubmit="return confirm(@js($giveConfirm));">
                        @csrf
                        <label class="form-label">Phone number for the app</label>
                        <div style="display:flex; gap:8px; align-items:flex-start;">
                            <div style="flex:1;">
                                <input type="text" name="app_phone" value="{{ old('app_phone', $appAccess->memberPhone ?? $member->phone) }}" maxlength="30"
                                       placeholder="e.g. 024 123 4567"
                                       class="form-control {{ $errors->has('app_phone') ? 'is-invalid' : '' }}">
                                @error('app_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn-primary btn-sm"><i class="fas fa-mobile-screen"></i> Give app login</button>
                        </div>
                    </form>
                @endif
            @endcan
        @endif

    </div>
</div>
