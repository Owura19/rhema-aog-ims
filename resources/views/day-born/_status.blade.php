{{-- A day-born week's badges. Expects: $week. --}}
@if($week->isPublished())
    <span class="badge badge-success">Published</span>
@else
    <span class="badge badge-gray">Draft</span>
@endif
@if($week->special_service)
    <span class="badge badge-info">Special service</span>
@endif
@if($week->transaction_id)
    <span class="badge badge-warning">Posted to finance</span>
@endif
