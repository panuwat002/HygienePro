<x-app-layout>
    @section('header', 'หมวดหมู่จุดตรวจ (Checkpoint Categories)')

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">จัดกลุ่มจุดตรวจเพื่อความสะดวกในการจัดการ</h5>
                </div>
                <a href="{{ route('checkpoint-categories.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill">
                    <i class="bi bi-tags-fill me-2"></i>เพิ่มหมวดหมู่
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
                                <th class="ps-4 py-3 text-muted fw-bold">ไอคอน</th>
                                <th class="py-3 text-muted fw-bold">ชื่อหมวดหมู่</th>
                                <th class="py-3 text-muted fw-bold">จำนวนจุดตรวจ</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $category)
                            <tr>
                                <td class="ps-4">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
                                        <i class="bi bi-{{ $category->icon ?? 'tag' }} text-primary"></i>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">{{ $category->name }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill border border-secondary-subtle">
                                        <i class="bi bi-list-stars me-1"></i> {{ $category->checkpoints_count }} รายการ
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('checkpoint-categories.edit', $category->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('checkpoint-categories.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบหมวดหมู่นี้?')">
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
                                    <i class="bi bi-collection display-6 mb-3 d-block"></i>
                                    ยังไม่มีข้อมูลหมวดหมู่
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
