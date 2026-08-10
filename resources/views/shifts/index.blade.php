<x-app-layout>
    @section('header', 'จัดการกะการทำงาน')

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">กำหนดช่วงเวลาการทำงานของพนักงาน</h5>
                </div>
                <a href="{{ route('shifts.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill">
                    <i class="bi bi-plus-lg me-2"></i>เพิ่มกะใหม่
                </a>
            </div>

            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-modern">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-muted fw-bold">ชื่อกะ</th>
                                <th class="py-3 text-muted fw-bold">เวลาเริ่มต้น</th>
                                <th class="py-3 text-muted fw-bold">เวลาสิ้นสุด</th>
                                <th class="py-3 text-muted fw-bold">จำนวนพนักงาน</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($shifts as $shift)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-dark">{{ $shift->shift_name }}</span>
                                </td>
                                <td data-label="เวลาเริ่มต้น">
                                    <span class="badge bg-info-subtle text-info px-3 py-2 rounded-pill border border-info-subtle">
                                        <i class="bi bi-clock me-1"></i> {{ date('H:i', strtotime($shift->start_time)) }}
                                    </span>
                                </td>
                                <td data-label="เวลาสิ้นสุด">
                                    <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-2 rounded-pill border border-warning-subtle">
                                        <i class="bi bi-clock-fill me-1"></i> {{ date('H:i', strtotime($shift->end_time)) }}
                                    </span>
                                </td>
                                <td data-label="จำนวนพนักงาน">
                                    <span class="text-secondary fw-medium">{{ $shift->employees_count ?? 0 }} คน</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('shifts.edit', $shift->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('shifts.destroy', $shift->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบกะนี้?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-light btn-sm px-3">
                                                <i class="bi bi-trash3 text-danger"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-muted">
                                    <i class="bi bi-calendar-x display-6 mb-3 d-block"></i>
                                    ยังไม่มีข้อมูลกะการทำงาน
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
