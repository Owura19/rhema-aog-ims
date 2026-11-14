{{-- A sermon's status badge. Expects: $sermon. --}}
@if($sermon->isPublished())
    <span class="badge badge-success">Published</span>
@else
    <span class="badge badge-gray">Draft</span>
@endif
