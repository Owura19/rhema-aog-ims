{{-- Announcement fields, shared by create and edit. Expects: $announcement. Times are UTC (Ghana time). --}}
<div class="card" style="margin-bottom:20px; max-width:760px;">
    <div class="card-body">
        <div style="margin-bottom:16px;">
            <label class="form-label">Title <span style="color:red;">*</span></label>
            <input type="text" name="title" value="{{ old('title', $announcement->title) }}" maxlength="120"
                   class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}">
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom:16px;">
            <label class="form-label">Message <span style="color:red;">*</span> <span style="color:#94a3b8; font-weight:400;">(plain text, up to 1,000 characters)</span></label>
            <textarea name="body" rows="5" maxlength="1000" class="form-control {{ $errors->has('body') ? 'is-invalid' : '' }}">{{ old('body', $announcement->body) }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom:16px;">
            <label class="form-label">"Learn more" link (optional, https)</label>
            <input type="text" name="link_url" value="{{ old('link_url', $announcement->link_url) }}" maxlength="500" placeholder="https://…"
                   class="form-control {{ $errors->has('link_url') ? 'is-invalid' : '' }}">
            @error('link_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="grid-2" style="margin-bottom:16px;">
            <div>
                <label class="form-label">Show from</label>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}"
                       class="form-control {{ $errors->has('starts_at') ? 'is-invalid' : '' }}">
                @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Empty: now.</div>
            </div>
            <div>
                <label class="form-label">Show until (optional)</label>
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}"
                       class="form-control {{ $errors->has('ends_at') ? 'is-invalid' : '' }}">
                @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <label style="font-size:14px; color:#1e293b;">
            <input type="hidden" name="is_pinned" value="0">
            <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned', $announcement->is_pinned) ? 'checked' : '' }}>
            Pin to the top in the app
        </label>
    </div>
</div>
