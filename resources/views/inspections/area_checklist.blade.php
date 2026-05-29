<x-app-layout>
    @section('header', 'แบบฟอร์มตรวจพื้นที่ / เครื่องจักร')

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <!-- Header -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 text-uppercase mb-1">เซสชันการตรวจพื้นที่ (Bulk Inspection)</h6>
                                <h2 class="fw-bold mb-0">รวมรายการตรวจ {{ count($inspectionData) }} หัวข้อ</h2>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-white text-primary rounded-pill fs-6 px-3 py-2">
                                    <i class="bi bi-building me-1"></i> {{ $department->dept_name }}
                                </span>
                                <div class="mt-2 small text-white-50">
                                    <i class="bi bi-clock me-1"></i> กะ: {{ ucfirst($session->shift) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($recleanFixMode))
                <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>โหมดแก้ไข Re-clean</strong> — บันทึกได้เฉพาะรายการที่ Supervisor สั่งแก้ไขเท่านั้น
                </div>
                @endif

                    @foreach($inspectionData as $data)
                        @php
                            $targetKey = "{$data->target_type}:{$data->target_id}";
                            $targetUniqueId = str_replace(':', '_', $targetKey);
                            $totalCp = 0;
                            foreach($data->checkpoints as $cItems) {
                                $totalCp += count($cItems);
                            }
                        @endphp
                        
                        <form action="{{ route('inspection.area.store', ['session' => $session->id, 'location' => $data->location->id ?? 0]) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="accordion mb-4" id="accordion_{{ $targetUniqueId }}">
                            <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden">
                                <h2 class="accordion-header" id="heading_{{ $targetUniqueId }}">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} bg-white p-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_{{ $targetUniqueId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse_{{ $targetUniqueId }}">
                                        <div class="d-flex align-items-center w-100 pe-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                                <i class="bi bi-{{ $data->target_type === 'machine' ? 'gear-wide-connected' : 'layers' }} fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h4 class="fw-bold mb-0 text-dark text-truncate fs-5">{{ $data->name }}</h4>
                                                <span class="text-muted small text-truncate d-block"><i class="bi bi-geo-alt me-1"></i>{{ $data->subtext }}</span>
                                            </div>
                                            <div class="text-end ms-auto mt-2 mt-md-0">
                                                @if(count($data->existing_logs) > 0)
                                                    <span class="badge bg-success rounded-pill fw-normal px-3 py-2"><i class="bi bi-check2-circle me-1"></i> ตรวจและบันทึกแล้ว</span>
                                                @else
                                                    <span class="badge bg-secondary text-white rounded-pill fw-normal px-3 py-2"><i class="bi bi-clock me-1"></i> รอการตรวจสอบ (คลิกเพื่อเริ่ม)</span>
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse_{{ $targetUniqueId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading_{{ $targetUniqueId }}" data-bs-parent="#accordion_{{ $targetUniqueId }}">
                                    <div class="accordion-body bg-light bg-opacity-50 p-3 p-md-4">
                                        @forelse($data->checkpoints as $categoryName => $items)
                                            <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden border-start border-4 border-primary">
                                                <div class="card-header bg-white py-3 px-4 border-bottom">
                                                    <h6 class="fw-bold text-dark m-0">{{ $categoryName ?: 'ทั่วไป' }}</h6>
                                                </div>
                                                <div class="card-body p-0 bg-white">
                                                    @foreach($items as $checkpoint)
                                                        @php
                                                            $existing = $data->existing_logs[$checkpoint->id] ?? null;
                                                            $reclean = $data->reclean_requests[$checkpoint->id] ?? null;
                                                            
                                                            $passChecked = $existing ? $existing->result === 'pass' : (!$reclean); 
                                                            $failChecked = $existing ? $existing->result === 'fail' : ($reclean ? true : false);
                                                            
                                                            $inputId = "input_{$targetUniqueId}_{$checkpoint->id}";
                                                        @endphp
                                                        <div class="p-4 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}">
                                                            @if($reclean)
                                                            <div class="mb-3 p-3 rounded-3 bg-warning bg-opacity-10 border-start border-4 border-warning shadow-sm">
                                                                <div class="fw-bold text-dark small mb-1"><i class="bi bi-arrow-repeat me-1"></i> ผู้อนุมัติสั่งแก้ไขใหม่:</div>
                                                                <div class="text-danger small fw-medium">{{ $reclean->verification_comment ?? 'กรุณาแก้ไขและถ่ายรูปใหม่' }}</div>
                                                            </div>
                                                            @endif

                                                            <h6 class="fw-bold mb-3">{{ $checkpoint->title }}</h6>

                                                            <div class="d-flex gap-2 mb-3">
                                                                <div class="form-check flex-fill p-0">
                                                                    <input class="btn-check target-input-{{ $targetUniqueId }}" type="radio" 
                                                                        name="results[targets][{{ $targetKey }}][{{ $checkpoint->id }}]" 
                                                                        id="pass_{{ $inputId }}" 
                                                                        value="pass" 
                                                                        {{ $passChecked ? 'checked' : '' }}
                                                                        onchange="toggleTargetDetails('{{ $targetUniqueId }}', {{ $checkpoint->id }}, 'pass')">
                                                                    <label class="btn btn-outline-success w-100 py-2 rounded-3" for="pass_{{ $inputId }}">
                                                                        <i class="bi bi-check-circle me-1"></i> ผ่าน
                                                                    </label>
                                                                </div>
                                                                <div class="form-check flex-fill p-0">
                                                                    <input class="btn-check target-input-{{ $targetUniqueId }}" type="radio" 
                                                                        name="results[targets][{{ $targetKey }}][{{ $checkpoint->id }}]" 
                                                                        id="fail_{{ $inputId }}" 
                                                                        value="fail" 
                                                                        {{ $failChecked ? 'checked' : '' }}
                                                                        onchange="toggleTargetDetails('{{ $targetUniqueId }}', {{ $checkpoint->id }}, 'fail')">
                                                                    <label class="btn btn-outline-danger w-100 py-2 rounded-3" for="fail_{{ $inputId }}">
                                                                        <i class="bi bi-x-circle me-1"></i> ไม่ผ่าน
                                                                    </label>
                                                                </div>
                                                            </div>

                                                            <div id="details_{{ $targetUniqueId }}_{{ $checkpoint->id }}" class="bg-light p-3 rounded-3 border {{ $failChecked ? '' : 'd-none' }}">
                                                                <div class="mb-2">
                                                                    <label class="form-label small fw-bold text-secondary">
                                                                        {{ $failChecked ? 'สาเหตุที่ไม่ผ่าน / การแก้ไข (Correction)*' : 'หมายเหตุ (Optional)' }}
                                                                    </label>
                                                                    <textarea name="notes[{{ $targetKey }}][{{ $checkpoint->id }}]" class="form-control form-control-sm" rows="2" placeholder="ระบุรายละเอียด...">{{ $existing ? $existing->correction_action : '' }}</textarea>
                                                                </div>
                                                                <div>
                                                                    <label class="form-label small fw-bold text-secondary">
                                                                        แนบรูปภาพ <span class="text-danger {{ $failChecked ? '' : 'd-none' }}">*</span>
                                                                    </label>
                                                                    <input type="file" name="photos[{{ $targetKey }}][{{ $checkpoint->id }}]" class="form-control form-control-sm" accept="image/*">
                                                                    @if($existing && $existing->photo_path)
                                                                        <div class="mt-2 small text-primary">
                                                                            <i class="bi bi-image"></i> มีรูปภาพเดิมแล้ว
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @empty
                                            <div class="alert alert-light border text-center py-4 rounded-4">
                                                <i class="bi bi-info-circle mb-2 d-block"></i>
                                                ยังไม่มีจุดตรวจสำหรับรายการนี้
                                            </div>
                                        @endforelse
                                        
                                        <div class="mt-4 pb-2 text-end">
                                            <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                                                <i class="bi bi-save2 me-1"></i> บันทึกข้อมูลเครื่องนี้
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </form>
                    @endforeach

                    @if(empty($recleanFixMode))
                    <form action="{{ route('inspection.area.store', ['session' => $session->id, 'location' => $inspectionData[0]->location->id ?? 0]) }}" method="POST">
                        @csrf
                        <div class="card border-0 shadow-lg rounded-4 mb-5 bg-white overflow-hidden">
                            <div class="card-body p-4 text-center">
                                <p class="text-muted mb-4">คลิก "จบงาน" หลังจากตรวจสอบและบันทึกข้อมูลทุกเครื่องจักรครบถ้วนแล้ว</p>
                                <div class="d-grid gap-3">
                                    <div class="d-flex gap-2">
                                         <button type="submit" formaction="{{ route('inspection.pause', $session->id) }}" class="btn btn-warning flex-fill py-3 rounded-pill fw-bold border text-dark">
                                            <i class="bi bi-pause-circle me-2"></i> พักการตรวจ (Pause)
                                        </button>
                                        <button type="submit" name="save_action" value="finish" class="btn btn-success flex-fill py-3 rounded-pill fw-bold">
                                             <i class="bi bi-check2-circle me-2"></i> จบงาน (Finish Session)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endif
            </div>
        </div>
    </div>

    <script>
        function toggleTargetDetails(targetId, cpId, status) {
            const container = document.getElementById(`details_${targetId}_${cpId}`);
            if (!container) return;
            
            const label = container.querySelector('label');
            const photoReq = container.querySelector('.text-danger');
            
            // Find inputs within this specific container
            const noteInput = container.querySelector('textarea');
            const photoInput = container.querySelector('input[type="file"]');

            if (status === 'fail') {
                container.classList.remove('d-none');
                if (label) label.innerText = 'สาเหตุที่ไม่ผ่าน / การแก้ไข (Correction)*';
                if (photoReq) photoReq.classList.remove('d-none');
                
                // Add Required
                if (noteInput) noteInput.setAttribute('required', 'required');
                if (photoInput) photoInput.setAttribute('required', 'required');
            } else {
                container.classList.add('d-none'); // Hide when pass is selected
                if (label) label.innerText = 'หมายเหตุ (Optional)';
                if (photoReq) photoReq.classList.add('d-none');
                
                // Remove Required
                if (noteInput) noteInput.removeAttribute('required');
                if (photoInput) photoInput.removeAttribute('required');
            }
        }

    </script>
</x-app-layout>
