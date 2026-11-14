@extends('layouts.app')

@section('title', 'Sermon Series')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('sermons.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Sermons
    </a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-layer-group" style="color:#2563eb; margin-right:8px;"></i>Sermon Series</div>
        <a href="{{ route('sermon-series.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Series</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Series</th>
                    <th>Sermons</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($series as $item)
                <tr>
                    <td>
                        <div style="font-weight:600; color:#1e293b;">{{ $item->title }}</div>
                        @if($item->description)
                            <div style="font-size:12px; color:#94a3b8;">{{ \Illuminate\Support\Str::limit($item->description, 90) }}</div>
                        @endif
                    </td>
                    <td>{{ $item->sermons_count }}</td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a href="{{ route('sermon-series.edit', $item) }}" class="btn-outline btn-sm"><i class="fas fa-edit"></i> Edit</a>
                        <form method="POST" action="{{ route('sermon-series.destroy', $item) }}" style="display:inline;"
                              onsubmit="return confirm(@js('Delete the series ' . $item->title . '? Its sermons are kept, without a series.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="text-align:center; padding:40px; color:#94a3b8;">No series yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
