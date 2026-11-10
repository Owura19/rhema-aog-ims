{{-- Theme form fields, shared by create and edit. Expects: $theme. --}}
<div class="card" style="margin-bottom:20px; max-width:720px;">
    <div class="card-body">
        <div class="grid-2">
            <div style="grid-column:span 2;">
                <label class="form-label">Title <span style="color:red;">*</span></label>
                <input type="text" name="title" value="{{ old('title', $theme->title) }}" maxlength="255" placeholder="e.g. Walking by Faith"
                       class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label">Starts on <span style="color:red;">*</span></label>
                <input type="date" name="starts_on" value="{{ old('starts_on', $theme->starts_on?->format('Y-m-d')) }}"
                       class="form-control {{ $errors->has('starts_on') ? 'is-invalid' : '' }}">
                @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label">Ends on <span style="color:red;">*</span></label>
                <input type="date" name="ends_on" value="{{ old('ends_on', $theme->ends_on?->format('Y-m-d')) }}"
                       class="form-control {{ $errors->has('ends_on') ? 'is-invalid' : '' }}">
                @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div style="grid-column:span 2;">
                <label class="form-label">Notes for writers (and the AI drafts)</label>
                <textarea name="notes" rows="4" maxlength="2000" class="form-control {{ $errors->has('notes') ? 'is-invalid' : '' }}">{{ old('notes', $theme->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
