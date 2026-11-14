{{-- Sermon series form fields, shared by create and edit. Expects: $seriesItem. --}}
<div class="card" style="margin-bottom:20px; max-width:720px;">
    <div class="card-body">
        <div style="margin-bottom:16px;">
            <label class="form-label">Title <span style="color:red;">*</span></label>
            <input type="text" name="title" value="{{ old('title', $seriesItem->title) }}" maxlength="150" placeholder="e.g. The Book of Acts"
                   class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}">
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="4" maxlength="2000" class="form-control {{ $errors->has('description') ? 'is-invalid' : '' }}">{{ old('description', $seriesItem->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
