<x-app-layout>
    @section('header', 'แบบฟอร์มตรวจพื้นที่ / เครื่องจักร')

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Location Header -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 text-uppercase mb-1">พื้นที่ (Location)</h6>
                                <h2 class="fw-bold mb-0">
                                    {{ $location->location_name }} 
                                    @if(isset($machine) && $machine)
                                        <span class="fs-4 text-white-50">/ {{ $machine->name }}</span>
                                    @endif
                                </h2>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-white text-primary rounded-pill fs-6 px-3 py-2">
                                    <i class="bi bi-building me-1"></i> {{ $department->dept_name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inspection Form -->
                <form action="{{ route('inspection.area.store', ['session' => $session->id, 'location' => $location->id]) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @if(isset($machine) && $machine)
                        <input type="hidden" name="machine_id" value="{{ $machine->id }}">
                    @endif
                    
                    @forelse($checkpoints as $categoryName => $items)
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                                <h5 class="fw-bold text-primary m-0 border-start border-4 border-primary ps-3">
                                    {{ $categoryName ?: 'ทั่วไป' }}
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                @foreach($items as $index => $checkpoint)
                                    @php
                                        // Check existing result
                                        $existing = $existingLogs[$checkpoint->id] ?? null;
                                        $passChecked = $existing ? $existing->result === 'pass' : true; // Default to pass
                                        $failChecked = $existing ? $existing->result === 'fail' : false;
                                    @endphp
                                    <div class="p-4 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="fw-bold mb-1">{{ $checkpoint->title }}</h6>
                                                @if($checkpoint->description)
                                                    <p class="text-muted small m-0">{{ $checkpoint->description }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="d-flex gap-3 mb-3">
                                            <div class="form-check flex-fill">
                                                <input class="form-check-input" type="radio" 
                                                    name="results[{{ $checkpoint->id }}]" 
                                                    id="pass_{{ $checkpoint->id }}" 
                                                    value="pass" 
                                                    {{ $passChecked ? 'checked' : '' }}
                                                    onchange="toggleDetails({{ $checkpoint->id }}, 'pass')">
                                                <label class="form-check-label w-100 p-3 rounded border text-center cursor-pointer radio-select-label" for="pass_{{ $checkpoint->id }}">
                                                    <i class="bi bi-check-circle-fill text-success fs-4 d-block mb-1"></i>
                                                    <span class="fw-bold text-success">ผ่าน</span>
                                                </label>
                                            </div>
                                            <div class="form-check flex-fill">
                                                <input class="form-check-input" type="radio" 
                                                    name="results[{{ $checkpoint->id }}]" 
                                                    id="fail_{{ $checkpoint->id }}" 
                                                    value="fail" 
                                                    {{ $failChecked ? 'checked' : '' }}
                                                    onchange="toggleDetails({{ $checkpoint->id }}, 'fail')">
                                                <label class="form-check-label w-100 p-3 rounded border text-center cursor-pointer radio-select-label" for="fail_{{ $checkpoint->id }}">
                                                    <i class="bi bi-x-circle-fill text-danger fs-4 d-block mb-1"></i>
                                                    <span class="fw-bold text-danger">ไม่ผ่าน</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- HIDDEN INPUTS SECTION -->
                                        <div id="details_{{ $checkpoint->id }}" class="bg-light p-3 rounded-3 border {{ $failChecked ? '' : 'd-none' }}">
                                            <div class="mb-2">
                                                <label class="form-label small fw-bold text-secondary" id="note_label_{{ $checkpoint->id }}">
                                                    {{ $failChecked ? 'สาเหตุที่ไม่ผ่าน / การแก้ไข (Correction)*' : 'หมายเหตุ (Optional)' }}
                                                </label>
                                                <textarea name="notes[{{ $checkpoint->id }}]" class="form-control" rows="2" placeholder="ระบุรายละเอียด..."></textarea>
                                            </div>
                                            <div>
                                                <label class="form-label small fw-bold text-secondary">
                                                    แนบรูปภาพประกอบ <span id="photo_req_{{ $checkpoint->id }}" class="{{ $failChecked ? '' : 'd-none' }} text-danger">*</span>
                                                </label>
                                                <input type="file" name="photos[{{ $checkpoint->id }}]" class="form-control" accept="image/*">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-info text-center rounded-4 py-5">
                            <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                            <h5>ยังไม่มีจุดตรวจสำหรับ{{ isset($machine) ? 'เครื่องจักร' : 'พื้นที่' }}นี้</h5>
                            <p class="mb-0">กรุณาเพิ่มจุดตรวจให้กับ{{ isset($machine) ? 'เครื่องจักร' : 'จุดประจำการ' }}นี้ก่อน</p>
                            @if(isset($machine))
                                <a href="{{ route('machines.index') }}" class="btn btn-outline-primary mt-3">ไปจัดการเครื่องจักร</a>
                            @else
                                <a href="{{ route('locations.map', $location->id) }}" class="btn btn-outline-primary mt-3">ไปหน้าจัดการจุดตรวจ</a>
                            @endif
                        </div>
                    @endforelse

                    @if($checkpoints->isNotEmpty())
                        <div class="d-grid pb-5">
                            <button type="submit" class="btn btn-primary-custom btn-lg shadow py-3 fs-5 rounded-pill">
                                <i class="bi bi-save me-2"></i> บันทึกผลการตรวจ
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <!-- Simple CSS for Radio Selection -->
    <style>
        .form-check-input { display: none; }
        .form-check-input:checked + label {
            background-color: #f8f9fa;
            border-color: currentColor !important;
            box-shadow: 0 0 0 2px currentColor;
        }
    </style>

    <script>
        function toggleDetails(id, status) {
            const detailsDiv = document.getElementById('details_' + id);
            const noteLabel = document.getElementById('note_label_' + id);
            const photoReq = document.getElementById('photo_req_' + id);

            if (status === 'fail') {
                detailsDiv.classList.remove('d-none');
                noteLabel.innerText = 'สาเหตุที่ไม่ผ่าน / การแก้ไข (Correction)*';
                photoReq.classList.remove('d-none');
            } else {
                // If Pass, user requested Optional Photo. So we keep it visible but optional?
                // Or maybe hide it by default and let them expand?
                // User said: "When Pass, want to attach photo (Optional)".
                // So it should be Visible (or easily accessible).
                // Let's keep it visible but change label.
                detailsDiv.classList.remove('d-none');
                noteLabel.innerText = 'หมายเหตุ (Optional)';
                photoReq.classList.add('d-none');
            }
        }
    </script>
</x-app-layout>
