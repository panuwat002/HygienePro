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
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="fw-bold mb-1">{{ $employee->fullname }}</h5>
                            @php
                                $status = $employee->getTrafficLightStatus();
                                $score = $employee->getHygieneScore();
                                $badgeColor = match($status) {
                                    'green' => 'bg-success',
                                    'yellow' => 'bg-warning text-dark',
                                    'red' => 'bg-danger',
                                };
                                $statusText = match($status) {
                                    'green' => 'Excellent',
                                    'yellow' => 'Watch List',
                                    'red' => 'Critical',
                                };
                            @endphp
                            <span class="badge {{ $badgeColor }} rounded-pill">{{ $statusText }} ({{ $score }}%)</span>
                        </div>
                        <p class="text-muted mb-0 small">{{ $employee->employee_id }} | {{ $employee->department->dept_name }}</p>
                    </div>
                </div>
            </div>

            <form action="{{ route('inspection.log.store', $session->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">

                <div class="d-flex flex-column gap-3">
                    
                    @if(isset($requiresRandomPhoto) && $requiresRandomPhoto && isset($randomEvidencePhoto))
                    <div class="card shadow-sm border-0 border-success border-start border-4 rounded-4 mb-2 animate-in">
                        <div class="card-body p-4 bg-success bg-opacity-10 text-center">
                            <h6 class="text-success fw-bold mb-3">
                                <i class="bi bi-shield-check me-2 fs-5"></i>ถ่ายภาพยืนยันตัวตน (Spot Check)
                            </h6>
                            <img src="{{ $randomEvidencePhoto }}" class="img-fluid rounded border border-success mb-2" style="max-height: 250px; object-fit: cover; width: 100%; max-width: 300px;" alt="Random Evidence">
                            <p class="small text-muted mb-0"><i class="bi bi-check-circle-fill text-success me-1"></i> ระบบบันทึกภาพถ่ายยืนยันตัวบุคคลหน้างานเรียบร้อยแล้ว</p>
                            <input type="hidden" name="random_evidence_photo_base64" value="{{ $randomEvidencePhoto }}">
                        </div>
                    </div>
                    @endif
                    @foreach($checkpoints as $cp)
                    @php
                        $oldLog = $existingLogs[$cp->id] ?? null;
                        $reclean = $recleanRequests[$cp->id] ?? null;
                        $isFail = ($oldLog && $oldLog->result === 'fail') || $reclean;
                    @endphp
                    <div x-data="{ status: '{{ $isFail ? 'fail' : 'pass' }}', showStandard: false }">
                        <div class="card shadow-sm border-0 rounded-4 transition-all" :class="status === 'fail' ? 'border border-danger border-2' : ''">
                            <input type="hidden" name="logs[{{ $cp->id }}][checkpoint_id]" value="{{ $cp->id }}">
                            <div class="card-body p-4">
                                <!-- Visual Standard Expansion -->
                                @if($cp->image_good || $cp->image_bad)
                                <div x-show="showStandard" x-transition class="mb-3 p-2 bg-light rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold small text-secondary"><i class="bi bi-info-circle me-1"></i>มาตรฐานการตรวจ (Visual Standard)</span>
                                        <button type="button" @click="showStandard = false" class="btn-close btn-sm"></button>
                                    </div>
                                    <div class="row g-2">
                                        @if($cp->image_good)
                                        <div class="col-6">
                                            <div class="text-center bg-white p-1 rounded border border-success border-opacity-25 h-100">
                                                <div class="text-success fw-bold small mb-1"><i class="bi bi-check-circle-fill me-1"></i>ถูกต้อง (Good)</div>
                                                <img src="{{ asset('storage/'.$cp->image_good) }}" class="img-fluid rounded" alt="Good Example" style="max-height: 150px; object-fit: contain;">
                                            </div>
                                        </div>
                                        @endif
                                        @if($cp->image_bad)
                                        <div class="col-6">
                                            <div class="text-center bg-white p-1 rounded border border-danger border-opacity-25 h-100">
                                                <div class="text-danger fw-bold small mb-1"><i class="bi bi-x-circle-fill me-1"></i>ไม่ถูกต้อง (Bad)</div>
                                                <img src="{{ asset('storage/'.$cp->image_bad) }}" class="img-fluid rounded" alt="Bad Example" style="max-height: 150px; object-fit: contain;">
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @endif
                                @if($reclean)
                                <div class="mb-3 p-2 rounded bg-warning bg-opacity-10 border-start border-4 border-warning">
                                    <div class="fw-bold text-dark small"><i class="bi bi-arrow-repeat me-1"></i> ผู้อนุมัติสั่งแก้ไขใหม่ (Re-clean Requested):</div>
                                    <div class="text-danger small">{{ $reclean->verification_comment ?? 'กรุณาแก้ไขและถ่ายรูปใหม่' }}</div>
                                </div>
                                @endif
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="pe-3">
                                        <h6 class="fw-bold mb-1">
                                            {{ $cp->title }}
                                            @if($cp->image_good || $cp->image_bad) 
                                                <a href="#" @click.prevent="showStandard = !showStandard" class="text-secondary ms-2 small text-decoration-none" style="font-size: 0.8em;">
                                                    <i class="bi bi-images me-1"></i>ดูตัวอย่าง
                                                </a>
                                            @endif
                                        </h6>
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
                                <!-- Fail or Re-clean Detail Section -->
                                <div x-show="status === 'fail' || '{{ $reclean ? '1' : '0' }}' === '1'" x-transition class="pt-3 border-top border-light">
                                    <div class="mb-3">
                                        <label class="form-label text-danger fw-bold small">
                                            @if($reclean)
                                                📸 Correction Proof (รูปถ่ายหลังแก้ไข) <span class="text-danger">*</span>
                                            @else
                                                📸 Evidence Photo (ถ่ายรูปหลักฐาน) <span class="text-danger">*</span>
                                            @endif
                                        </label>
                                        <input type="file" class="form-control" name="logs[{{ $cp->id }}][photo]" accept="image/*" capture="environment" 
                                            :required="status === 'fail' || '{{ $reclean ? '1' : '0' }}' === '1'">
                                    </div>
                                    <div>
                                        <label class="form-label text-danger fw-bold small">
                                            @if($reclean)
                                                📝 Action Taken (สิ่งที่ได้แก้ไข) <span class="text-danger">*</span>
                                            @else
                                                📝 Correction Action (การแก้ไขเบื้องต้น) <span class="text-danger">*</span>
                                            @endif
                                        </label>
                                        <textarea class="form-control" name="logs[{{ $cp->id }}][correction]" rows="2" placeholder="ระบุวิธีแก้ไข..." 
                                            :required="status === 'fail' || '{{ $reclean ? '1' : '0' }}' === '1'">{{ $oldLog->correction_action ?? '' }}</textarea>
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
    @include('inspections.alert_script')
</x-app-layout>
