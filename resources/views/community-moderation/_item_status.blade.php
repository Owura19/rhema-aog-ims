{{-- Status badge of a community post or comment. Expects: $item. --}}
@if($item->trashed())
    <span class="badge badge-gray">Deleted by author</span>
@elseif($item->isHidden())
    <span class="badge badge-danger">{{ $item->hidden_automatically ? 'Hidden automatically' : 'Hidden' }}</span>
@else
    <span class="badge badge-success">Visible</span>
@endif
