<x-app-layout>
    @section('header', 'รายการรออนุมัติ (Pending Approvals)')

    <div class="row">
        @if(session('success'))
            <div class="col-12 mb-4">
                <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>{{ session('success') }}</div>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="col-12 mb-4">
                <div class="alert alert-danger border-0 shadow-sm rounded-3 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>{{ session('error') }}</div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="table-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">ชื่อสายการอนุมัติ (Flow Name)</th>
                                    <th>ประเภทคำขอ (Request Type)</th>
                                    <th>รายละเอียด (Details)</th>
                                    <th>ขั้นตอนที่ (Step)</th>
                                    <th class="text-end pe-4">การจัดการ (Action)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $req)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                                    <i class="bi bi-file-earmark-check"></i>
                                                </div>
                                                <span class="fw-bold">{{ $req->flow->name }}</span>
                                            </div>
                                        </td>
                                        <td data-label="ประเภทคำขอ">
                                            <span class="badge bg-secondary">{{ class_basename($req->approvable_type) }}</span>
                                        </td>
                                        <td data-label="รายละเอียด">
                                            <span class="fw-bold text-dark d-block">ID: {{ $req->approvable_id }}</span>
                                            @if(method_exists($req->approvable, 'getApprovalDetails'))
                                                <div class="small text-muted mt-1">{!! nl2br(e($req->approvable->getApprovalDetails())) !!}</div>
                                            @endif
                                        </td>
                                        <td data-label="ขั้นตอนที่">
                                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">ขั้นตอนที่ {{ $req->current_step_order }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form action="{{ route('approvals.approve', $req->id) }}" method="POST" class="d-inline-block no-loading">
                                                @csrf
                                                <button type="button" class="btn btn-sm btn-success rounded-3 me-2" onclick="window.confirmAction(this, 'คุณแน่ใจหรือไม่ที่จะอนุมัติรายการนี้?', 'อนุมัติ', 'success')">
                                                    <i class="bi bi-check-circle me-1"></i> อนุมัติ
                                                </button>
                                            </form>
                                            <form action="{{ route('approvals.reject', $req->id) }}" method="POST" class="d-inline-block no-loading">
                                                @csrf
                                                <input type="hidden" name="reason" class="reject-reason">
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-3" onclick="confirmReject(this.closest('form'))">
                                                    <i class="bi bi-x-circle me-1"></i> ปฏิเสธ
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="text-success opacity-50 mb-3"><i class="bi bi-check2-all" style="font-size: 4rem;"></i></div>
                                            <h5 class="fw-bold text-dark">เรียบร้อย! ไม่มีรายการรออนุมัติ</h5>
                                            <p class="text-muted mb-0">คุณได้ดำเนินการอนุมัติทุกรายการเรียบร้อยแล้วในขณะนี้</p>
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

    @push('scripts')
    <script>
        function confirmReject(form) {
            Swal.fire({
                title: 'ปฏิเสธการอนุมัติ',
                input: 'textarea',
                inputLabel: 'ระบุเหตุผล (ไม่บังคับ)',
                inputPlaceholder: 'พิมพ์เหตุผลที่นี่...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยันปฏิเสธ',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.querySelector('.reject-reason').value = result.value || '';
                    
                    const btn = form.querySelector('button');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> ...';
                    
                    form.submit();
                }
            });
        }
    </script>
    @endpush
</x-app-layout>
