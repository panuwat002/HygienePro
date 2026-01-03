<x-app-layout>
    @section('header', 'การจัดการเครื่องจักร / พื้นที่ย่อย (Machines)')

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">รายการเครื่องจักรในระบบ</h5>
                    <p class="text-muted small mb-0">Manage machines for specific inspection points</p>
                </div>
                <a href="{{ route('machines.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill">
                    <i class="bi bi-gear-wide-connected me-2"></i>เพิ่มเครื่องจักรใหม่
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
                                <th class="ps-4 py-3 text-muted fw-bold" style="width: 80px;">รูปภาพ</th>
                                <th class="py-3 text-muted fw-bold">ชื่อเครื่องจักร / พื้นที่ย่อย</th>
                                <th class="py-3 text-muted fw-bold">รหัส (Code)</th>
                                <th class="py-3 text-muted fw-bold">สังกัด (Location)</th>
                                <th class="py-3 text-muted fw-bold text-center">สถานะ</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($machines as $machine)
                            <tr>
                                <td class="ps-4">
                                    <div class="rounded-3 bg-light d-flex justify-content-center align-items-center border overflow-hidden" style="width: 50px; height: 50px;">
                                        @if($machine->image)
                                            <img src="{{ asset('storage/' . $machine->image) }}" class="w-100 h-100 object-fit-cover" alt="Machine">
                                        @else
                                            <i class="bi bi-gear text-secondary fs-5"></i>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block">{{ $machine->name }}</span>
                                    <small class="text-muted">{{ Str::limit($machine->description, 30) }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary font-monospace">{{ $machine->code ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-info bg-opacity-10 text-info fw-normal">
                                        <i class="bi bi-geo-alt me-1"></i> {{ $machine->location->location_name ?? 'Unassigned' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($machine->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2">Active</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2">Inactive</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('machines.map', $machine->id) }}" class="btn btn-primary btn-sm px-3 border-end" title="กำหนดจุดตรวจ">
                                            <i class="bi bi-diagram-3"></i>
                                        </a>
                                        <a href="{{ route('machines.edit', $machine->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('machines.destroy', $machine->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันลบเครื่องจักรนี้? ข้อมูลการตรวจที่เกี่ยวข้องอาจได้รับผลกระทบ')">
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
                                <td colspan="6" class="py-5 text-center text-muted">
                                    <i class="bi bi-gear-wide display-6 mb-3 d-block opacity-25"></i>
                                    ยังไม่มีข้อมูลเครื่องจักร
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
