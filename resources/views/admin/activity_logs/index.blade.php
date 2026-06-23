<x-app-layout>
    @section('header', 'ประวัติการใช้งานระบบ (Audit Logs)')

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 p-4 pb-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">System Activity Logs</h5>
            </div>

            <form action="{{ route('activity-logs.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="small text-muted">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <option value="create" {{ request('action') == 'create' ? 'selected' : '' }}>Create</option>
                        <option value="update" {{ request('action') == 'update' ? 'selected' : '' }}>Update</option>
                        <option value="delete" {{ request('action') == 'delete' ? 'selected' : '' }}>Delete</option>
                        <option value="login" {{ request('action') == 'login' ? 'selected' : '' }}>Login</option>
                        <option value="change_assignee" {{ request('action') == 'change_assignee' ? 'selected' : '' }}>Change Assignee</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    <a href="{{ route('activity-logs.index') }}" class="btn btn-light btn-sm w-100 mt-1">Reset</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>Description</th>
                            <th class="text-end pe-4">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-4 text-nowrap">
                                <span class="d-block fw-bold">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                                <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light p-1 me-2" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-person text-secondary"></i>
                                    </div>
                                    <span class="fw-bold">{{ $log->user->name ?? 'System/Deleted' }}</span>
                                </div>
                            </td>
                            <td>
                                @php
                                    $badgeClass = match($log->action) {
                                        'create' => 'success',
                                        'update' => 'primary',
                                        'delete' => 'danger',
                                        'login' => 'info',
                                        'change_assignee' => 'warning',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }} bg-opacity-10 text-{{ $badgeClass }} rounded-pill px-3">{{ ucfirst($log->action) }}</span>
                            </td>
                            <td>
                                <small class="text-muted font-monospace">{{ class_basename($log->model_type) }} #{{ $log->model_id }}</small>
                            </td>
                            <td>{{ Str::limit($log->description, 50) }}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" data-bs-toggle="modal" data-bs-target="#logModal{{ $log->id }}">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <!-- Modal -->
                                <div class="modal fade" id="logModal{{ $log->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content text-start">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Activity Details #{{ $log->id }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="fw-bold text-muted small">User Agent</label>
                                                        <p class="small text-break mb-0">{{ $log->user_agent }}</p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="fw-bold text-muted small">IP Address</label>
                                                        <p class="small mb-0">{{ $log->ip_address }}</p>
                                                    </div>
                                                    <div class="col-12">
                                                        <hr>
                                                        <h6 class="fw-bold">Changes</h6>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded text-danger bg-opacity-10 border border-danger border-opacity-25 h-100">
                                                                    <strong class="text-danger d-block mb-2">Old Values</strong>
                                                                    <pre class="small mb-0 text-dark">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded text-success bg-opacity-10 border border-success border-opacity-25 h-100">
                                                                    <strong class="text-success d-block mb-2">New Values</strong>
                                                                    <pre class="small mb-0 text-dark">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No activity logs found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-top">
                {{ $logs->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</x-app-layout>
