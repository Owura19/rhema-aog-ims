{{-- An announcement's state badge. Expects: $announcement. --}}
@switch($announcement->state())
    @case('active')
        <span class="badge badge-success">Showing in app</span>
        @break
    @case('scheduled')
        <span class="badge badge-info">Scheduled</span>
        @break
    @case('expired')
        <span class="badge badge-warning">Expired</span>
        @break
    @default
        <span class="badge badge-gray">Draft</span>
@endswitch
