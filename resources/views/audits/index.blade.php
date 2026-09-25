<x-app-layout>
    @section('header', 'ตารางสุ่มตรวจ')

    @php
        $badges = [
            \App\Models\RandomAudit::COMPLETED   => ['ตรวจแล้ว', 'success', 'bi-check-circle-fill'],
            \App\Models\RandomAudit::IN_PROGRESS => ['กำลังตรวจ', 'primary', 'bi-hourglass-split'],
            \App\Models\RandomAudit::PENDING     => ['รอตรวจ', 'warning', 'bi-clock'],
            \App\Models\RandomAudit::MISSED      => ['ไม่ได้ตรวจ', 'danger', 'bi-x-circle-fill'],
        ];
    @endphp

    <div class="container-fluid px-lg-4 py-4">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-bullseye me-2 text-warning"></i>ตารางสุ่มตรวจ สัปดาห์ที่ {{ $week }}/{{ $year }}</h4>
                <p class="text-muted small mb-0">
                    ระบบสุ่มวัน กะ และแผนก ไว้ล่วงหน้าทุกเช้ามืดวันจันทร์ หน้านี้คือหลักฐานว่าแผนถูกทำจริงหรือไม่
                </p>
            </div>

            <div class="btn-group shadow-sm">
                <a class="btn btn-outline-secondary"
                   href="{{ route('audits.index', ['week' => $previousWeek->isoWeek(), 'year' => $previousWeek->year]) }}">
                    <i class="bi bi-chevron-left"></i> สัปดาห์ก่อน
                </a>
                <a class="btn btn-outline-secondary"
                   href="{{ route('audits.index') }}">สัปดาห์นี้</a>
                <a class="btn btn-outline-secondary"
                   href="{{ route('audits.index', ['week' => $nextWeek->isoWeek(), 'year' => $nextWeek->year]) }}">
                    สัปดาห์หน้า <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach($badges as $status => [$label, $colour, $icon])
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi {{ $icon }} fs-3 text-{{ $colour }}"></i>
                        <div>
                            <div class="fs-4 fw-bold lh-1">{{ $counts[$status] ?? 0 }}</div>
                            <small class="text-muted">{{ $label }}</small>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>วันที่</th>
                            <th>แผนก</th>
                            <th>กะ</th>
                            <th class="text-center">สุ่ม</th>
                            <th>สถานะ</th>
                            <th>ผู้ตรวจ</th>
                            <th>รอบตรวจที่ใช้เป็นหลักฐาน</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($audits as $audit)
                        @php [$label, $colour, $icon] = $badges[$audit->status] ?? ['-', 'secondary', 'bi-question']; @endphp
                        <tr>
                            <td class="text-nowrap">
                                {{ $audit->audit_date->format('D d/m/Y') }}
                                @if($audit->audit_date->isToday())
                                    <span class="badge bg-warning text-dark ms-1">วันนี้</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $audit->department->dept_name ?? '—' }}</td>
                            <td>{{ $audit->shift_label }}</td>
                            <td class="text-center">{{ $audit->sample_size }}</td>
                            <td class="text-nowrap">
                                <span class="badge bg-{{ $colour }}-subtle text-{{ $colour }}-emphasis border border-{{ $colour }}-subtle">
                                    <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                                </span>
                                {{-- The scheduled command retires these at 07:00; until it runs
                                     the row still reads 'รอตรวจ' and would look outstanding. --}}
                                @if($audit->is_overdue)
                                    <span class="badge bg-danger-subtle text-danger-emphasis ms-1">เลยกำหนด</span>
                                @endif
                            </td>
                            <td>{{ $audit->auditor->name ?? '—' }}</td>
                            <td>
                                @if($audit->session)
                                    @php
                                        $round = 'รอบที่ ' . $audit->session->round . ' (' . $audit->session->type . ')';
                                    @endphp
                                    {{-- A department supervisor may be told to do an audit
                                         without being allowed to pull reports, so the round
                                         is named either way and only linked when it opens. --}}
                                    @can('view-reports')
                                        <a class="text-decoration-none" href="{{ route('reports.daily', [
                                            'date' => $audit->audit_date->toDateString(),
                                            'department_id' => $audit->department_id,
                                        ]) }}">{{ $round }}</a>
                                    @else
                                        {{ $round }}
                                    @endcan
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-25"></i>
                                สัปดาห์นี้ยังไม่มีการสุ่มตรวจ — ระบบจะสุ่มให้เช้ามืดวันจันทร์
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-muted small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            ภารกิจจะเปลี่ยนเป็น <strong>กำลังตรวจ</strong> เมื่อ QA ระดับหัวหน้าขึ้นไปเปิดรอบตรวจของแผนกนั้นในวันและกะที่สุ่มไว้
            และเป็น <strong>ตรวจแล้ว</strong> เมื่อปิดรอบ หากผ่านวันไปโดยไม่มีใครตรวจ ระบบจะบันทึกเป็น <strong>ไม่ได้ตรวจ</strong>
        </p>
    </div>
</x-app-layout>
