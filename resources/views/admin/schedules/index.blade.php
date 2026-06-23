<x-app-layout>
    @section('header', 'Inspection Schedules')

    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('schedules.create') }}" class="btn btn-primary-custom shadow-sm" title="สร้างตารางตรวจ (Create Schedule)">
                    <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline ms-2">สร้างตารางตรวจ (Create Schedule)</span>
                </a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="table-modern">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 border-0 text-secondary fw-bold" style="width: 25%;">หัวข้อ (Title)</th>
                                        <th class="border-0 text-secondary fw-bold">แผนก (Department)</th>
                                        <th class="border-0 text-secondary fw-bold">เป้าหมาย (Target)</th>
                                        <th class="border-0 text-secondary fw-bold">ความถี่ (Frequency)</th>
                                        <th class="border-0 text-secondary fw-bold">เวลา (Time)</th>
                                        <th class="border-0 text-secondary fw-bold">สถานะ (Status)</th>
                                        <th class="pe-4 border-0 text-secondary fw-bold text-end">จัดการ (Actions)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($schedules as $schedule)
                                        <tr>
                                            <td class="ps-4" data-label="หัวข้อ (Title)">
                                                <span class="fw-bold text-dark">{{ $schedule->title }}</span>
                                            </td>
                                            <td data-label="แผนก (Department)">
                                                <span class="badge bg-light text-dark border">{{ $schedule->department->dept_name ?? '-' }}</span>
                                            </td>
                                            <td data-label="เป้าหมาย (Target)">
                                                @if($schedule->targetable_type === \App\Models\Location::class)
                                                    <i class="bi bi-geo-alt text-danger me-1"></i> Area: {{ $schedule->targetable->location_name ?? 'N/A' }}
                                                @elseif($schedule->targetable_type === \App\Models\Machine::class)
                                                    <i class="bi bi-gear-wide-connected text-primary me-1"></i> Machine: {{ $schedule->targetable->name ?? 'N/A' }}
                                                @endif
                                            </td>
                                            <td data-label="ความถี่ (Frequency)">
                                                <span class="text-capitalize">{{ $schedule->frequency }}</span>
                                                @if($schedule->frequency === 'weekly' && $schedule->days_of_week)
                                                    <div class="small text-muted">
                                                        {{ implode(', ', $schedule->days_of_week) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td data-label="เวลา (Time)">
                                                <i class="bi bi-clock me-1 text-muted"></i>
                                                {{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }} - 
                                                {{ \Carbon\Carbon::parse($schedule->end_time)->format('H:i') }}
                                            </td>
                                            <td data-label="สถานะ (Status)">
                                                @if($schedule->is_active)
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Active</span>
                                                @else
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="pe-4 text-end">
                                                <a href="{{ route('schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-primary rounded-pill me-2">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="{{ route('schedules.destroy', $schedule) }}" method="POST" class="d-inline-block" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบรายการนี้?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="bi bi-calendar-x display-4 mb-3 d-block text-secondary opacity-50"></i>
                                                ยังไม่มีตารางการตรวจ (No Schedules Found)
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
    </div>
</x-app-layout>
