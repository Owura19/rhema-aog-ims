@extends('layouts.app')

@section('title', 'Devotion Themes')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('devotions.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Devotions
    </a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-tags" style="color:#2563eb; margin-right:8px;"></i>Devotion Themes</div>
        <a href="{{ route('devotion-themes.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Theme</a>
    </div>
    <div style="padding:12px 24px; font-size:13px; color:#64748b; border-bottom:1px solid #f1f5f9;">
        <i class="fas fa-circle-info"></i> Themes may overlap: for any date, the theme that started most recently applies.
        @if($current)
            Today's theme: <strong>{{ $current->title }}</strong>.
        @else
            No theme covers today.
        @endif
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Theme</th>
                    <th>Dates</th>
                    <th>Devotions</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($themes as $theme)
                <tr>
                    <td>
                        <div style="font-weight:600; color:#1e293b;">{{ $theme->title }}</div>
                        @if($theme->notes)
                            <div style="font-size:12px; color:#94a3b8;">{{ \Illuminate\Support\Str::limit($theme->notes, 90) }}</div>
                        @endif
                    </td>
                    <td style="font-size:13px; color:#64748b; white-space:nowrap;">
                        {{ $theme->starts_on->format('j M Y') }} – {{ $theme->ends_on->format('j M Y') }}
                        @if($current && $current->is($theme))
                            <span class="badge badge-success">current</span>
                        @endif
                    </td>
                    <td>{{ $theme->devotions_count }}</td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a href="{{ route('devotion-themes.edit', $theme) }}" class="btn-outline btn-sm"><i class="fas fa-edit"></i> Edit</a>
                        <form method="POST" action="{{ route('devotion-themes.destroy', $theme) }}" style="display:inline;"
                              onsubmit="return confirm(@js('Delete the theme ' . $theme->title . '? Its devotions keep their text but will show no theme.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center; padding:40px; color:#94a3b8;">No themes yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
