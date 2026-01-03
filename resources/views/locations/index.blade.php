<x-app-layout>
    @section('header', 'การจัดการจุดประจำการ (Locations)')

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">กำหนดจุดหรือสถานีงานสำหรับพนักงาน</h5>
                </div>
                <a href="{{ route('locations.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill">
                    <i class="bi bi-geo-alt-fill me-2"></i>เพิ่มจุดประจำการ
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
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-muted fw-bold">ชื่อจุดประจำการ</th>
                                <th class="py-3 text-muted fw-bold">คำอธิบาย</th>
                                <th class="py-3 text-muted fw-bold">จำนวนจุดตรวจ</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($locations as $location)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-dark">{{ $location->location_name }}</span>
                                </td>
                                <td>
                                    <span class="text-secondary small">{{ $location->description ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill border border-primary-subtle">
                                        <i class="bi bi-list-check me-1"></i> {{ $location->checkpoints_count }} จุด
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('locations.map', $location->id) }}" class="btn btn-primary btn-sm px-3 border-end" title="ตั้งค่าจุดตรวจ">
                                            <i class="bi bi-diagram-3"></i>
                                        </a>
                                        <a href="{{ route('locations.edit', $location->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('locations.destroy', $location->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบจุดนี้?')">
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
                                <td colspan="4" class="py-5 text-center text-muted">
                                    <i class="bi bi-geo display-6 mb-3 d-block"></i>
                                    ยังไม่มีข้อมูลจุดประจำการ
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
