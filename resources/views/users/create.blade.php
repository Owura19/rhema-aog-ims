@extends('layouts.app')
@section('title', 'Add Staff')

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header">
        <div class="card-title">Add New Staff Account</div>
        <a href="{{ route('users.index') }}" class="btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div style="margin-bottom:18px;">
                <label class="form-label">Full Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}"
                       class="form-control @error('name') is-invalid @enderror" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label class="form-label">Email Address <span style="color:#ef4444;">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror" required>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       class="form-control @error('phone') is-invalid @enderror" placeholder="e.g. 024 123 4567">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label class="form-label">Role <span style="color:#ef4444;">*</span></label>
                <select name="role" class="form-control @error('role') is-invalid @enderror" required>
                    <option value="">— Select a role —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label class="form-label">Password <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror" required>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div style="font-size:12px; color:#64748b; margin-top:4px;">Minimum 8 characters.</div>
            </div>

            <div style="margin-bottom:24px;">
                <label class="form-label">Confirm Password <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-user-plus"></i> Create Staff Account
            </button>
        </form>
    </div>
</div>
@endsection