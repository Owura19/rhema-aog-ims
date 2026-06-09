@extends('layouts.app')
@section('title', 'Edit Staff')

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header">
        <div class="card-title">Edit Staff Account</div>
        <a href="{{ route('users.index') }}" class="btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom:18px;">
                <label class="form-label">Full Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                       class="form-control @error('name') is-invalid @enderror" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label class="form-label">Email Address <span style="color:#ef4444;">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                       class="form-control @error('email') is-invalid @enderror" required>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                       class="form-control @error('phone') is-invalid @enderror" placeholder="e.g. 024 123 4567">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label class="form-label">Role <span style="color:#ef4444;">*</span></label>
                <select name="role" class="form-control @error('role') is-invalid @enderror" required>
                    <option value="">— Select a role —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}"
                            {{ old('role', $user->getRoleNames()->first()) === $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="border-top:1px solid #f1f5f9; margin:22px 0; padding-top:18px;">
                <div style="font-size:13px; color:#64748b; margin-bottom:14px;">
                    <i class="fas fa-lock"></i> Leave password fields blank to keep the current password.
                </div>

                <div style="margin-bottom:18px;">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Minimum 8 characters.</div>
                </div>

                <div style="margin-bottom:8px;">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Update Account
            </button>
        </form>
    </div>
</div>
@endsection