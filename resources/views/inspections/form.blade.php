<x-app-layout>
    @section('header', 'Inspection Checklist')

    @push('styles')
    <style>
        /* Modern Segmented Control Styles */
        .segmented-control {
            display: flex;
            background-color: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            width: 140px;
            flex-shrink: 0;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .segmented-control input[type="radio"] {
            display: none;
        }
        .segmented-control label {
            flex: 1;
            text-align: center;
            padding: 6px 0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
            margin: 0;
        }
        .segmented-control input[type="radio"][value="pass"]:checked + label {
            background-color: #10b981;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16,185,129,0.3), 0 2px 4px -1px rgba(16,185,129,0.2);
        }
        .segmented-control input[type="radio"][value="fail"]:checked + label {
            background-color: #ef4444;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(239,68,68,0.3), 0 2px 4px -1px rgba(239,68,68,0.2);
        }
        
        /* Glassmorphism Header */
        .sticky-glass-header {
            position: sticky;
            top: 0; /* Adjust if there's a top navbar */
            z-index: 1020;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 10px 0;
            margin-bottom: 1rem;
        }

        /* Compact Cards */
        .checklist-item {
            transition: all 0.2s ease;
            border: 1px solid #f1f5f9;
        }
        .checklist-item:hover {
            border-color: #e2e8f0;
            background-color: #f8fafc;
        }
        .checklist-item.is-failed {
            border-color: #fca5a5;
            background-color: #fef2f2;
        }
        
        /* Fixed Bottom Action */
        .fixed-bottom-action {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid #e2e8f0;
            padding: 1rem;
            z-index: 1030;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.03);
        }
    </style>
    @endpush

    <div class="row justify-content-center" style="padding-bottom: 100px;">
        <div class="col-md-8 col-lg-6 px-0 px-md-3">
            
            <!-- Sticky Employee Header (Glassmorphism) -->
            @if(session('error'))
            <div class="alert alert-danger mx-3 mx-md-0 mt-3 mb-0 rounded-3 shadow-sm border-0 d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                <div>
                    <strong>ข้อผิดพลาด!</strong><br>
                    <span class="small">{{ session('error') }}</span>
                </div>
            </div>
            @endif

            <div class="sticky-glass-header px-3 px-md-0">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-clipboard-check me-1"></i> Inspection</h6>
                    <a href="{{ route('inspection.scan', $session->id) }}" class="btn btn-sm btn-light text-muted rounded-pill px-3">
                        <i class="bi bi-x-lg me-1"></i> ยกเลิก
                    </a>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-light d-flex justify-content-center align-items-center flex-shrink-0 shadow-sm" style="width:50px; height:50px; border: 2px solid white;">
                        @if($employee->profile_image)
                            <img src="{{ $employee->profile_image }}" class="rounded-circle w-100 h-100 object-fit-cover">
                        @else
                            <i class="bi bi-person-fill text-secondary fs-3"></i>
                        @endif
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold mb-0 text-truncate text-dark" style="font-size: 1rem;">{{ $employee->fullname }}</h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small text-truncate">{{ $employee->employee_id }}</span>
                            @php
                                $status = $employee->getTrafficLightStatus();
                                $badgeColor = match($status) { 'green' => 'bg-success', 'yellow' => 'bg-warning text-dark', 'red' => 'bg-danger' };
                                $statusIcon = match($status) { 'green' => '🟢', 'yellow' => '🟡', 'red' => '🔴' };
                            @endphp
                            <span class="badge {{ $badgeColor }} bg-opacity-10 text-dark border border-light rounded-pill x-small px-2 shadow-sm">
                                {{ $statusIcon }} {{ $employee->getHygieneScore() }}%
                            </span>
                        </div>
                        
                        <!-- Loop Engineering Fix: Dynamic Location Selector -->
                        <div class="mt-2">
                            <select id="locationSelector" class="form-select form-select-sm rounded-pill shadow-sm bg-primary bg-opacity-10 text-primary border-0 x-small fw-bold" style="max-width: 220px; cursor: pointer;" onchange="changeLocation(this.value)">
                                <option value="">-- ไม่ระบุจุดประจำการ --</option>
                                @foreach($departmentLocations as $loc)
                                    <option value="{{ $loc->id }}" @selected($currentLocationId == $loc->id)>📍 {{ $loc->location_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <form id="inspectionForm" action="{{ route('inspection.log.store', ['session' => $session->id] + request()->query()) }}" method="POST" enctype="multipart/form-data" class="px-3 px-md-0">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <!-- Hidden input to save the overridden location to Master Data -->
                <input type="hidden" name="location_id" value="{{ $currentLocationId }}">

                <div class="d-flex flex-column gap-2">
                    
                    @if(isset($requiresRandomPhoto) && $requiresRandomPhoto && isset($randomEvidencePhoto))
                    <div class="card shadow-sm border-0 border-success border-start border-4 rounded-4 mb-3 animate-in">
                        <div class="card-body p-3 bg-success bg-opacity-10 d-flex gap-3 align-items-center">
                            <img src="{{ $randomEvidencePhoto }}" class="rounded shadow-sm object-fit-cover" style="width: 60px; height: 60px;" alt="Random Evidence">
                            <div>
                                <h6 class="text-success fw-bold mb-1 small"><i class="bi bi-shield-check me-1"></i> ถ่ายภาพยืนยันตัวตนแล้ว</h6>
                                <p class="x-small text-muted mb-0">ระบบบันทึกภาพถ่ายยืนยันตัวบุคคลหน้างานเรียบร้อยแล้ว (Spot Check)</p>
                            </div>
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
                        <div class="card checklist-item shadow-sm rounded-4" :class="status === 'fail' ? 'is-failed shadow' : ''">
                            <input type="hidden" name="logs[{{ $cp->id }}][checkpoint_id]" value="{{ $cp->id }}">
                            <div class="card-body p-3">
                                
                                @if($reclean)
                                <div class="mb-2 p-2 rounded bg-warning bg-opacity-10 border-start border-3 border-warning">
                                    <div class="fw-bold text-dark x-small"><i class="bi bi-arrow-repeat me-1"></i> ผู้อนุมัติสั่งแก้ไขใหม่:</div>
                                    <div class="text-danger x-small">{{ $reclean->verification_comment ?? 'กรุณาแก้ไขและถ่ายรูปใหม่' }}</div>
                                </div>
                                @endif

                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="pe-1 flex-grow-1 min-w-0">
                                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.9rem;">
                                            {{ $cp->title }}
                                        </h6>
                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            <p class="text-muted x-small mb-0 text-truncate" style="max-width: 180px;">{{ $cp->description }}</p>
                                            @if($cp->image_good || $cp->image_bad) 
                                                <a href="#" @click.prevent="showStandard = !showStandard" class="text-primary x-small text-decoration-none bg-primary bg-opacity-10 px-2 py-1 rounded-pill">
                                                    <i class="bi bi-image me-1"></i>ตัวอย่าง
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Modern iOS-style Segmented Control -->
                                    <div class="segmented-control">
                                        <input type="radio" name="logs[{{ $cp->id }}][result]" value="pass" id="pass_{{ $cp->id }}"
                                            @checked(!$isFail) x-model="status">
                                        <label for="pass_{{ $cp->id }}"><i class="bi bi-check-lg"></i> ผ่าน</label>

                                        <input type="radio" name="logs[{{ $cp->id }}][result]" value="fail" id="fail_{{ $cp->id }}"
                                            @checked($isFail) x-model="status">
                                        <label for="fail_{{ $cp->id }}"><i class="bi bi-x-lg"></i> ไม่ผ่าน</label>
                                    </div>
                                </div>

                                <!-- Visual Standard Expansion -->
                                @if($cp->image_good || $cp->image_bad)
                                <div x-show="showStandard" x-transition.duration.300ms class="mt-3 p-2 bg-white rounded-3 border shadow-sm" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                        <span class="fw-bold x-small text-secondary"><i class="bi bi-info-circle me-1"></i>มาตรฐาน (Standard)</span>
                                        <button type="button" @click="showStandard = false" class="btn-close" style="font-size: 0.5rem;"></button>
                                    </div>
                                    <div class="row g-2">
                                        @if($cp->image_good)
                                        <div class="col-6">
                                            <div class="text-center bg-light p-1 rounded border border-success border-opacity-25 h-100">
                                                <div class="text-success fw-bold x-small mb-1"><i class="bi bi-check-circle-fill me-1"></i>ถูกต้อง</div>
                                                <img src="{{ asset('storage/'.$cp->image_good) }}" class="img-fluid rounded w-100" style="height: 100px; object-fit: cover;">
                                            </div>
                                        </div>
                                        @endif
                                        @if($cp->image_bad)
                                        <div class="col-6">
                                            <div class="text-center bg-light p-1 rounded border border-danger border-opacity-25 h-100">
                                                <div class="text-danger fw-bold x-small mb-1"><i class="bi bi-x-circle-fill me-1"></i>ไม่ถูกต้อง</div>
                                                <img src="{{ asset('storage/'.$cp->image_bad) }}" class="img-fluid rounded w-100" style="height: 100px; object-fit: cover;">
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                <!-- Fail Detail Section -->
                                <div x-show="status === 'fail' || '{{ $reclean ? '1' : '0' }}' === '1'" x-transition.duration.300ms class="pt-3 mt-2 border-top border-danger border-opacity-25" style="display: none;">
                                    <div class="mb-2">
                                        <label class="form-label text-danger fw-bold x-small mb-1">
                                            @if($reclean) 📸 รูปหลังแก้ไข <span class="text-danger">*</span> @else 📸 ถ่ายรูปหลักฐาน <span class="text-danger">*</span> @endif
                                        </label>
                                        <input type="file" class="form-control form-control-sm border-danger border-opacity-50" name="logs[{{ $cp->id }}][photo]" accept="image/*" capture="environment" 
                                            :required="status === 'fail' || '{{ $reclean ? '1' : '0' }}' === '1'">
                                    </div>
                                    <div>
                                        <label class="form-label text-danger fw-bold x-small mb-1">
                                            @if($reclean) 📝 สิ่งที่แก้ไขแล้ว <span class="text-danger">*</span> @else 📝 การแก้ไขเบื้องต้น <span class="text-danger">*</span> @endif
                                        </label>
                                        <textarea class="form-control form-control-sm border-danger border-opacity-50" name="logs[{{ $cp->id }}][correction]" rows="2" placeholder="ระบุเหตุผลหรือวิธีแก้ไข..." 
                                            :required="status === 'fail' || '{{ $reclean ? '1' : '0' }}' === '1'">{{ $oldLog->correction_action ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <!-- Spacer for fixed bottom -->
                <div style="height: 60px;"></div>

                <!-- Fixed Bottom Submit Action -->
                <div class="fixed-bottom-action d-flex justify-content-center">
                    <div class="w-100" style="max-width: 600px;">
                        <button type="submit" id="submit-inspection-btn" class="btn btn-primary btn-lg w-100 shadow rounded-pill fw-bold" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); border: none;">
                             <i class="bi bi-save me-1"></i> บันทึกผลการตรวจ
                        </button>
                    </div>
                </div>
            </form>
            
            <script>
                document.getElementById('inspectionForm').addEventListener('submit', function() {
                    var btn = document.getElementById('submit-inspection-btn');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังบันทึกข้อมูล...';
                });
            </script>
        </div>
    </div>
    @include('inspections.alert_script')
</x-app-layout>

@push('scripts')
<script>
    // Loop Engineering Fix: Reload checklist with new location checkpoints
    function changeLocation(locationId) {
        Swal.fire({
            title: 'กำลังโหลดจุดตรวจใหม่...',
            text: 'รอสักครู่ ระบบกำลังเปลี่ยนจุดประจำการให้ครับ',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        const url = new URL(window.location.href);
        url.searchParams.set('location_id', locationId);
        window.location.href = url.toString();
    }
</script>
@endpush
