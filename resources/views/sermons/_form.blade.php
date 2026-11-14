{{--
    Sermon form fields, shared by create and edit.
    Expects: $sermon, $series, $members. The form must use enctype="multipart/form-data".
--}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-microphone" style="color:#2563eb; margin-right:8px;"></i>Sermon</div>
    </div>
    <div class="card-body">
        <div class="grid-3">
            <div style="grid-column:span 2;">
                <label class="form-label">Title <span style="color:red;">*</span></label>
                <input type="text" name="title" value="{{ old('title', $sermon->title) }}" maxlength="255"
                       class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Preached on <span style="color:red;">*</span></label>
                <input type="date" name="preached_on" value="{{ old('preached_on', $sermon->preached_on?->format('Y-m-d')) }}"
                       class="form-control {{ $errors->has('preached_on') ? 'is-invalid' : '' }}">
                @error('preached_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Preacher <span style="color:red;">*</span></label>
                <input type="text" name="preacher_name" value="{{ old('preacher_name', $sermon->preacher_name) }}" maxlength="150" placeholder="e.g. Rev. Mensah"
                       class="form-control {{ $errors->has('preacher_name') ? 'is-invalid' : '' }}">
                @error('preacher_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Preacher's member record (optional)</label>
                <select name="preacher_member_id" class="form-control {{ $errors->has('preacher_member_id') ? 'is-invalid' : '' }}">
                    <option value="">Not linked</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" {{ (string) old('preacher_member_id', $sermon->preacher_member_id) === (string) $member->id ? 'selected' : '' }}>
                            {{ $member->first_name }} {{ $member->last_name }}
                        </option>
                    @endforeach
                </select>
                @error('preacher_member_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Series</label>
                <select name="series_id" class="form-control {{ $errors->has('series_id') ? 'is-invalid' : '' }}">
                    <option value="">No series</option>
                    @foreach($series as $item)
                        <option value="{{ $item->id }}" {{ (string) old('series_id', $sermon->series_id) === (string) $item->id ? 'selected' : '' }}>{{ $item->title }}</option>
                    @endforeach
                </select>
                @error('series_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Scripture reference (optional)</label>
                <input type="text" name="scripture_reference" value="{{ old('scripture_reference', $sermon->scripture_reference) }}" maxlength="255" placeholder="e.g. Acts 2:1-13"
                       class="form-control {{ $errors->has('scripture_reference') ? 'is-invalid' : '' }}">
                @error('scripture_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">YouTube link</label>
                <input type="text" name="youtube_url" value="{{ old('youtube_url', $sermon->youtubeUrl()) }}" maxlength="255" placeholder="https://youtu.be/…"
                       class="form-control {{ $errors->has('youtube_url') ? 'is-invalid' : '' }}">
                @error('youtube_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Audio link (https)</label>
                <input type="text" name="audio_url" value="{{ old('audio_url', $sermon->audio_url) }}" maxlength="500" placeholder="https://…/sermon.mp3"
                       class="form-control {{ $errors->has('audio_url') ? 'is-invalid' : '' }}">
                @error('audio_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">A sermon needs a YouTube link or an audio link before it can be published.</div>
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Summary / notes</label>
                <textarea name="summary" rows="6" maxlength="10000" class="form-control {{ $errors->has('summary') ? 'is-invalid' : '' }}">{{ old('summary', $sermon->summary) }}</textarea>
                @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Thumbnail image (optional; JPG, PNG or WebP, up to 4 MB)</label>
                @if($sermon->thumbnailUrl())
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                        <img src="{{ $sermon->thumbnailUrl() }}" alt="" style="width:160px; height:90px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0;">
                        @if($sermon->thumbnail_path)
                            <label style="font-size:13px; color:#64748b;">
                                <input type="checkbox" name="remove_thumbnail" value="1"> Remove the uploaded image
                            </label>
                        @else
                            <span style="font-size:12px; color:#94a3b8;">Using the YouTube thumbnail.</span>
                        @endif
                    </div>
                @endif
                <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp"
                       class="form-control {{ $errors->has('thumbnail') ? 'is-invalid' : '' }}">
                @error('thumbnail')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
