<x-app-layout>
    @section('header', 'Edit Inspection Schedule')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    
                    <form method="POST" action="{{ route('schedules.update', $schedule) }}">
                        @csrf
                        @method('PUT')

                        <!-- Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold">ชื่อตาราง (Schedule Title)</label>
                            <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $schedule->title) }}" required>
                            @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <!-- Department -->
                        <div class="mb-3">
                            <label for="department_id" class="form-label fw-bold">แผนก (Department)</label>
                            <select name="department_id" id="department_id" class="form-select" required>
                                <option value="">เลือกแผนก (Select Department)</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id', $schedule->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->dept_name }}</option>
                                @endforeach
                            </select>
                            @error('department_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <!-- Target Type -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภทเป้าหมาย (Target Type)</label>
                            <div class="mt-1">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="target_type" id="type_location" value="location" onchange="toggleTargetSelect()" {{ old('target_type', strtolower(class_basename($schedule->targetable_type)) === 'location' ? 'location' : '') == 'location' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="type_location">จุดประจำการ (Location/Area)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="target_type" id="type_machine" value="machine" onchange="toggleTargetSelect()" {{ old('target_type', strtolower(class_basename($schedule->targetable_type)) === 'machine' ? 'machine' : '') == 'machine' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="type_machine">เครื่องจักร (Machine)</label>
                                </div>
                            </div>
                        </div>

                        <!-- Target Selection -->
                        <div class="mb-3">
                            <label for="target_id" class="form-label fw-bold">เลือกเป้าหมาย (Select Target)</label>
                            
                            <select name="target_id" id="target_id_location" class="form-select">
                                <option value="">เลือกจุดประจำการ (Select Location)</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ old('target_id', $schedule->targetable_id) == $location->id ? 'selected' : '' }}>{{ $location->location_name }}</option>
                                @endforeach
                            </select>

                            <select name="target_id" id="target_id_machine" class="form-select d-none" disabled>
                                <option value="">เลือกเครื่องจักร (Select Machine)</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}" {{ old('target_id', $schedule->targetable_id) == $machine->id ? 'selected' : '' }}>{{ $machine->name }}</option>
                                @endforeach
                            </select>
                            @error('target_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <!-- Frequency -->
                        <div class="mb-3">
                            <label for="frequency" class="form-label fw-bold">ความถี่ (Frequency)</label>
                            <select name="frequency" id="frequency" class="form-select" onchange="toggleDays()" required>
                                <option value="daily" {{ old('frequency', $schedule->frequency) == 'daily' ? 'selected' : '' }}>ทุกวัน (Daily)</option>
                                <option value="weekly" {{ old('frequency', $schedule->frequency) == 'weekly' ? 'selected' : '' }}>รายสัปดาห์ (Weekly)</option>
                            </select>
                        </div>

                        <!-- Days of Week (Hidden unless Weekly) -->
                        <div class="mb-3 d-none" id="days_wrapper">
                            <label class="form-label fw-bold mb-2">เลือกวัน (Days of Week)</label>
                            <div class="d-flex flex-wrap gap-3 p-3 border rounded bg-light">
                                @php $selectedDays = $schedule->days_of_week ?? []; @endphp
                                @foreach(['Mon'=>'จันทร์', 'Tue'=>'อังคาร', 'Wed'=>'พุธ', 'Thu'=>'พฤหัส', 'Fri'=>'ศุกร์', 'Sat'=>'เสาร์', 'Sun'=>'อาทิตย์'] as $key => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="days_of_week[]" value="{{ $key }}" id="day_{{ $key }}" {{ is_array($selectedDays) && in_array($key, $selectedDays) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="day_{{ $key }}">{{ $key }} ({{ $label }})</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Time Window -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label fw-bold">เวลาเริ่ม (Start Time)</label>
                                <input type="time" name="start_time" id="start_time" class="form-control" value="{{ old('start_time', \Carbon\Carbon::parse($schedule->start_time)->format('H:i')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label fw-bold">เวลาสิ้นสุด (End Time)</label>
                                <input type="time" name="end_time" id="end_time" class="form-control" value="{{ old('end_time', \Carbon\Carbon::parse($schedule->end_time)->format('H:i')) }}" required>
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $schedule->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">ใช้งานตารางนี้ (Active)</label>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('schedules.index') }}" class="btn btn-light border">ยกเลิก (Cancel)</a>
                            <button type="submit" class="btn btn-dark">
                                <i class="bi bi-save me-1"></i> อัปเดตข้อมูล (Update Schedule)
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTargetSelect() {
            const locRadio = document.getElementById('type_location');
            const locSelect = document.getElementById('target_id_location');
            const macSelect = document.getElementById('target_id_machine');

            if (locRadio.checked) {
                locSelect.classList.remove('d-none');
                locSelect.disabled = false;
                macSelect.classList.add('d-none');
                macSelect.disabled = true;
            } else {
                locSelect.classList.add('d-none');
                locSelect.disabled = true;
                macSelect.classList.remove('d-none');
                macSelect.disabled = false;
            }
        }

        function toggleDays() {
            const freq = document.getElementById('frequency').value;
            const daysWrapper = document.getElementById('days_wrapper');
            
            if (freq === 'weekly') {
                daysWrapper.classList.remove('d-none');
            } else {
                daysWrapper.classList.add('d-none');
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            toggleTargetSelect();
            toggleDays();
        });
    </script>
</x-app-layout>
