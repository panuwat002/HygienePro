<x-app-layout>
    @section('header', 'ทวนสอบผลการตรวจ (Verification)')

    <div class="row">
        <div class="col-12">
            <!-- Filter Section -->
            <!-- Filter Section -->
            <div class="card bg-white rounded-4 mb-4">
                <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-funnel text-primary fs-5"></i>
                        <span class="fw-bold text-dark">ตัวกรองข้อมูล</span>
                    </div>
                    <form action="{{ route('inspection.verification') }}" method="GET" class="d-flex gap-2 w-100 w-md-auto">
                        <div class="input-group input-group-sm shadow-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar"></i></span>
                            <input type="date" class="form-control border-start-0 ps-0" name="date" value="{{ $date }}">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm">
                            ค้นหา
                        </button>
                    </form>
                </div>
            </div>

            <!-- Table Section -->
            <div class="card bg-white rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="min-width: 800px;">
                            <thead class="bg-light border-bottom">
                                <tr>
                                    <th class="ps-4 py-3 text-muted fw-bold" style="width: 100px;">เวลา</th>
                                    <th class="py-3 text-muted fw-bold">รอบ/กะ</th> <!-- Added Header -->
                                    <th class="py-3 text-muted fw-bold">พนักงาน</th>
                                    <th class="py-3 text-muted fw-bold">แผนก</th>
                                    <th class="py-3 text-muted fw-bold text-center">ผลการตรวจ</th>
                                    <th class="py-3 text-muted fw-bold text-center">สถานะทวนสอบ</th> <!-- New Column -->
                                    <th class="py-3 text-muted fw-bold">ข้อบกพร่อง (Findings)</th>
                                    <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($groupedInspections as $group)
                                <tr>
                                    <td class="ps-4 fw-normal text-muted">{{ $group->time }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-light text-dark mb-1 border">รอบที่ {{ $group->round }}</span>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">{{ $group->shift }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-3 overflow-hidden border" style="width:40px; height:40px;">
                                                @if($group->image_path)
                                                    <img src="{{ asset('storage/' . $group->image_path) }}" alt="{{ $group->name }}" class="w-100 h-100 object-fit-cover">
                                                @elseif($group->type === 'area')
                                                    <i class="bi bi-gear-wide-connected text-secondary fs-5"></i>
                                                @else
                                                    <span class="fw-bold text-secondary">{{ substr($group->name, 0, 1) }}</span>
                                                @endif
                                            </div>
                                            <span class="fw-bold text-dark">{{ $group->name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-secondary">{{ $group->subtext }}</td>
                                    <td class="text-center">
                                        @if($group->status === 'pass')
                                            <span class="badge badge-soft-success px-3 py-2 rounded-pill fw-normal">
                                                <i class="bi bi-check-circle me-1"></i> ผ่าน
                                            </span>
                                        @else
                                            <span class="badge badge-soft-danger px-3 py-2 rounded-pill fw-normal">
                                                <i class="bi bi-x-circle me-1"></i> ไม่ผ่าน
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($group->is_verified)
                                            <span class="badge badge-soft-primary px-3 py-2 rounded-pill fw-normal">
                                                <i class="bi bi-shield-check me-1"></i> ยืนยันแล้ว
                                            </span>
                                        @else
                                            <span class="badge badge-soft-warning px-3 py-2 rounded-pill fw-normal">
                                                <i class="bi bi-hourglass-split me-1"></i> รอยืนยัน
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($group->findings->isEmpty())
                                            <span class="text-muted">-</span>
                                        @else
                                            @foreach($group->findings as $log)
                                                <span class="text-danger small d-block mb-1">• {{ $log->checkpoint->title ?? 'Unknown' }}</span>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button class="btn btn-link text-primary text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#detailModal{{ $group->modal_id }}">
                                            <i class="bi bi-eye"></i> รายละเอียด
                                        </button>
                                    </td>
                                </tr>

                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-clipboard-x fs-1 d-block mb-3"></i>
                                        ไม่พบข้อมูลการตรวจในวันนี้
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
