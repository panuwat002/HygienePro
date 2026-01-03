<x-app-layout>
    @section('header', 'Inspection Checklist')

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('inspection.scan', $session->id) }}" class="btn btn-light text-muted">
                    <i class="bi bi-x-lg"></i> ยกเลิก
                </a>
            </div>

            <!-- Employee Card -->
            <div class="card shadow-sm border-0 mb-4 bg-white rounded-4">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-circle bg-light d-flex justify-content-center align-items-center" style="width:70px; height:70px;">
                        @if($employee->profile_image)
                            <img src="{{ $employee->profile_image }}" class="rounded-circle" width="70" height="70">
                        @else
                            <i class="bi bi-person-fill text-secondary fs-1"></i>
                        @endif
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">{{ $employee->fullname }}</h5>
                        <p class="text-muted mb-0 small">{{ $employee->employee_id }} | {{ $employee->department->dept_name }}</p>
                    </div>
                </div>
            </div>

            <form action="{{ route('inspection.log.store', $session->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">

                <div class="d-flex flex-column gap-3">
                    @foreach($checkpoints as $cp)
                    @php
                        $oldLog = $existingLogs[$cp->id] ?? null;
                        $isFail = $oldLog && $oldLog->result === 'fail';
                    @endphp
                    <div x-data="{ status: '{{ $isFail ? 'fail' : 'pass' }}' }">
                        <div class="card shadow-sm border-0 rounded-4 transition-all" :class="status === 'fail' ? 'border border-danger border-2' : ''">
                            <input type="hidden" name="logs[{{ $cp->id }}][checkpoint_id]" value="{{ $cp->id }}">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="pe-3">
                                        <h6 class="fw-bold mb-1">{{ $cp->title }}</h6>
                                        <p class="text-muted small mb-0">{{ $cp->description }}</p>
                                    </div>
                                    
                                    <!-- Toggle Button -->
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="logs[{{ $cp->id }}][result]" value="pass" id="pass_{{ $cp->id }}" 
                                            @checked(!$isFail) x-model="status">
                                        <label class="btn btn-outline-success btn-sm px-3" for="pass_{{ $cp->id }}"><i class="bi bi-check-lg"></i> ผ่าน</label>

                                        <input type="radio" class="btn-check" name="logs[{{ $cp->id }}][result]" value="fail" id="fail_{{ $cp->id }}" 
                                            @checked($isFail) x-model="status">
                                        <label class="btn btn-outline-danger btn-sm px-3" for="fail_{{ $cp->id }}"><i class="bi bi-x-lg"></i> ไม่ผ่าน</label>
                                    </div>
                                </div>

                                <!-- Fail Detail Section (Show only if Fail) -->
                                <div x-show="status === 'fail'" x-transition class="pt-3 border-top border-light">
                                    <div class="mb-3">
                                        <label class="form-label text-danger fw-bold small">📸 Evidence Photo (ถ่ายรูปหลักฐาน)</label>
                                        <input type="file" class="form-control" name="logs[{{ $cp->id }}][photo]" accept="image/*" capture="environment">
                                    </div>
                                    <div>
                                        <label class="form-label text-danger fw-bold small">📝 Correction Action (การแก้ไขเบื้องต้น)</label>
                                        <textarea class="form-control" name="logs[{{ $cp->id }}][correction]" rows="2" placeholder="ระบุวิธีแก้ไข...">{{ $oldLog->correction_action ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="d-grid mt-5 pb-5">
                    <button type="submit" class="btn btn-primary-custom btn-lg shadow">
                         บันทึกผลการตรวจ <i class="bi bi-save ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
