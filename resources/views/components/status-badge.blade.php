@if($status == 'green')
    <span class="badge bg-success">Normal</span>
@elseif($status == 'yellow')
    <span class="badge bg-warning text-dark">Watch List</span>
@else
    <span class="badge bg-danger">Critical</span>
@endif
