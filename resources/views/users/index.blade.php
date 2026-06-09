@extends('layouts.app')
@section('title', 'User Management')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-title">Staff Accounts</div>
        <a href="{{ route('users.create') }}" class="btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add Staff
        </a>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td style="font-weight:600; color:#1e293b;">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone ?? '—' }}</td>
                    <td>
                        <span class="badge badge-info">{{ $user->getRoleNames()->first() ?? 'No role' }}</span>
                    </td>
                    <td>
                        @if($user->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        <a href="{{ route('users.edit', $user) }}" class="btn-outline btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>

                        @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.toggle-active', $user) }}" style="display:inline;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="{{ $user->is_active ? 'btn-danger' : 'btn-accent' }} btn-sm">
                                    <i class="fas fa-{{ $user->is_active ? 'ban' : 'check' }}"></i>
                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('users.destroy', $user) }}" style="display:inline;"
                                  onsubmit="return confirm('Permanently delete {{ $user->name }}? This cannot be undone. Consider deactivating instead.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        @else
                            <span class="badge badge-gray">You</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:32px; color:#64748b;">
                        No staff accounts yet. <a href="{{ route('users.create') }}" style="color:var(--primary);">Add the first one</a>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
        <div style="padding:16px 24px;">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection