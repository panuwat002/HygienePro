<x-app-layout>
    @section('header', 'จัดการจุดตรวจ (Checkpoints)')

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">รายการคำถามหรือจุดตรวจทางสุขอนามัยในระบบ</h5>
                </div>
                <a href="{{ route('checkpoints.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill">
                    <i class="bi bi-plus-lg me-2"></i>เพิ่มจุดตรวจใหม่
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
                                <th class="ps-4 py-3 text-muted fw-bold">หมวดหมู่</th>
                                <th class="py-3 text-muted fw-bold">ชื่อจุดตรวจ</th>
                                <th class="py-3 text-muted fw-bold">คำอธิบาย</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($checkpoints as $cp)
                            <tr>
                                <td class="ps-4">
                                    @if($cp->category)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <i class="bi bi-{{ $cp->category->icon ?? 'tag' }} me-1"></i> {{ $cp->category->name }}
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">{{ $cp->title }}</span>
                                </td>
                                <td>
                                    <span class="text-secondary small">{{ $cp->description ?? '-' }}</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('checkpoints.edit', $cp->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('checkpoints.destroy', $cp->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบจุดตรวจนี้?')">
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
                                    <i class="bi bi-list-columns-reverse display-6 mb-3 d-block"></i>
                                    ยังไม่มีข้อมูลจุดตรวจ
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
