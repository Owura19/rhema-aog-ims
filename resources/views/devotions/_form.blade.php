{{--
    Devotion form fields, shared by create and edit.
    Expects: $devotion, $themes, $selectedTheme.
    verse_text is never a form field: the server fills it from the KJV when the reference is saved.
--}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-book-open" style="color:#2563eb; margin-right:8px;"></i>Devotion</div>
    </div>
    <div class="card-body">
        <div class="grid-3">
            <div>
                <label class="form-label">Date <span style="color:red;">*</span></label>
                <input type="date" name="publish_date" value="{{ old('publish_date', $devotion->publish_date?->format('Y-m-d')) }}"
                       class="form-control {{ $errors->has('publish_date') ? 'is-invalid' : '' }}">
                @error('publish_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Title <span style="color:red;">*</span></label>
                <input type="text" name="title" value="{{ old('title', $devotion->title) }}" maxlength="255"
                       class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Scripture reference <span style="color:red;">*</span></label>
                <input type="text" name="scripture_reference" value="{{ old('scripture_reference', $devotion->scripture_reference) }}"
                       placeholder="e.g. John 3:16-18 or Psalm 23" maxlength="255"
                       class="form-control {{ $errors->has('scripture_reference') ? 'is-invalid' : '' }}">
                @error('scripture_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">One passage within one chapter, up to {{ config('devotions.scripture.max_verses', 30) }} verses.</div>
            </div>

            <div>
                <label class="form-label">Theme</label>
                <select name="theme_id" class="form-control {{ $errors->has('theme_id') ? 'is-invalid' : '' }}">
                    <option value="">Automatic: the theme covering the date</option>
                    @foreach($themes as $theme)
                        <option value="{{ $theme->id }}" {{ (string) old('theme_id', $selectedTheme) === (string) $theme->id ? 'selected' : '' }}>
                            {{ $theme->title }} ({{ $theme->starts_on->format('j M') }} – {{ $theme->ends_on->format('j M Y') }})
                        </option>
                    @endforeach
                </select>
                @error('theme_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Author</label>
                <input type="text" name="author_name" value="{{ old('author_name', $devotion->author_name) }}" placeholder="e.g. Rev. Mensah" maxlength="255"
                       class="form-control {{ $errors->has('author_name') ? 'is-invalid' : '' }}">
                @error('author_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Verse (KJV)</label>
                @if($devotion->verse_text)
                    <div data-field="verse" style="padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; color:#374151;">
                        <div style="font-size:12px; font-weight:700; color:#2563eb; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">{{ $devotion->scripture_reference }}</div>
                        {{ $devotion->verse_text }}
                    </div>
                    <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Filled in from the KJV. Change the reference above to change the verse.</div>
                @else
                    <div style="font-size:13px; color:#94a3b8;"><i class="fas fa-circle-info"></i> The KJV verse fills in when you save, from the reference above.</div>
                @endif
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Reflection <span style="color:red;">*</span>
                    <span style="font-weight:400; color:#94a3b8;">(<span data-word-count-for="body">0</span> words; usually 250–350)</span></label>
                <textarea name="body" rows="12" data-word-count-source="body"
                          class="form-control {{ $errors->has('body') ? 'is-invalid' : '' }}">{{ old('body', $devotion->body) }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Prayer <span style="color:red;">*</span>
                    <span style="font-weight:400; color:#94a3b8;">(<span data-word-count-for="prayer">0</span> words; usually 40–80)</span></label>
                <textarea name="prayer" rows="4" data-word-count-source="prayer"
                          class="form-control {{ $errors->has('prayer') ? 'is-invalid' : '' }}">{{ old('prayer', $devotion->prayer) }}</textarea>
                @error('prayer')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Thought for the day <span style="color:red;">*</span>
                    <span style="font-weight:400; color:#94a3b8;">(one line, at most 140 characters)</span></label>
                <input type="text" name="thought_for_the_day" value="{{ old('thought_for_the_day', $devotion->thought_for_the_day) }}" maxlength="140"
                       class="form-control {{ $errors->has('thought_for_the_day') ? 'is-invalid' : '' }}">
                @error('thought_for_the_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<script>
    // Live word counts, counted the same way as the server (App\Support\Devotions\Words).
    document.querySelectorAll('[data-word-count-source]').forEach(function (field) {
        var target = document.querySelector('[data-word-count-for="' + field.dataset.wordCountSource + '"]');
        var update = function () {
            var text = field.value.trim();
            target.textContent = text === '' ? 0 : text.split(/\s+/).length;
        };
        field.addEventListener('input', update);
        update();
    });
</script>
