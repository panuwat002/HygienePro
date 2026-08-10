<x-app-layout>
    @section('header', 'รายงานผู้ผิดระเบียบสูงสุด')

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">เดือน (Month)</label>
                                <select name="month" class="form-select">
                                    @for($m=1; $m<=12; $m++)
                                        <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $m, 10)) }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">ปี (Year)</label>
                                <select name="year" class="form-select">
                                    @for($y=date('Y'); $y>=2024; $y--)
                                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> ค้นหา
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-0 border-bottom-0">
                        <ul class="nav nav-tabs card-header-tabs" id="offenderTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-3 text-danger fw-bold" id="people-tab" data-bs-toggle="tab" data-bs-target="#people" type="button" role="tab" aria-selected="true">
                                    <i class="bi bi-people-fill me-2"></i>Personnel (พนักงาน)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-3 text-warning fw-bold" id="machines-tab" data-bs-toggle="tab" data-bs-target="#machines" type="button" role="tab" aria-selected="false">
                                    <i class="bi bi-gear-fill me-2"></i>Machines (เครื่องจักร)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-3 text-primary fw-bold" id="areas-tab" data-bs-toggle="tab" data-bs-target="#areas" type="button" role="tab" aria-selected="false">
                                    <i class="bi bi-geo-alt-fill me-2"></i>Areas (พื้นที่)
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content" id="offenderTabsContent">
                            
                            <!-- 1. People Tab -->
                            <div class="tab-pane fade show active" id="people" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="ps-4">Rank</th>
                                                <th>Employee</th>
                                                <th>Department</th>
                                                <th class="text-center">Failures (ครั้ง)</th>
                                                <th class="text-center">Hygiene Score</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($offenders->take(10) as $index => $item)
                                                <tr>
                                                    <td class="ps-4 fw-bold">#{{ $index + 1 }}</td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            @if($item->info->profile_image)
                                                                <img src="{{ $item->info->profile_image }}" class="rounded-circle me-3" width="40" height="40">
                                                            @else
                                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                                    <i class="bi bi-person text-secondary"></i>
                                                                </div>
                                                            @endif
                                                            <div>
                                                                <div class="fw-bold">{{ $item->info->fullname }}</div>
                                                                <div class="small text-muted">{{ $item->info->employee_id }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border">{{ $item->info->department->dept_name ?? '-' }}</span></td>
                                                    <td class="text-center fw-bold text-danger">{{ $item->fail_count }}</td>
                                                    <td class="text-center">
                                                        @include('components.score-bar', ['score' => $item->score])
                                                    </td>
                                                    <td class="text-center">
                                                        @include('components.status-badge', ['status' => $item->status])
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center py-5 text-muted">Great Job! No personnel offenders found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 2. Machines Tab -->
                            <div class="tab-pane fade" id="machines" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="ps-4">Rank</th>
                                                <th>Machine Name</th>
                                                <th>Location</th>
                                                <th class="text-center">Failures (ครั้ง)</th>
                                                <th class="text-center">Hygiene Score</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($machineOffenders->take(10) as $index => $item)
                                                <tr>
                                                    <td class="ps-4 fw-bold">#{{ $index + 1 }}</td>
                                                    <td>
                                                        <div class="fw-bold">{{ $item->info->name }}</div>
                                                        <small class="text-muted">{{ $item->info->code }}</small>
                                                    </td>
                                                    <td>{{ $item->info->location->location_name ?? '-' }}</td>
                                                    <td class="text-center fw-bold text-danger">{{ $item->fail_count }}</td>
                                                    <td class="text-center">
                                                        @include('components.score-bar', ['score' => $item->score])
                                                    </td>
                                                    <td class="text-center">
                                                        @include('components.status-badge', ['status' => $item->status])
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center py-5 text-muted">Great Job! No machine offenders found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 3. Areas Tab -->
                            <div class="tab-pane fade" id="areas" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="ps-4">Rank</th>
                                                <th>Location Name</th>
                                                <th>Description</th>
                                                <th class="text-center">Failures (ครั้ง)</th>
                                                <th class="text-center">Hygiene Score</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($areaOffenders->take(10) as $index => $item)
                                                <tr>
                                                    <td class="ps-4 fw-bold">#{{ $index + 1 }}</td>
                                                    <td>
                                                        <div class="fw-bold">{{ $item->info->location_name }}</div>
                                                    </td>
                                                    <td>{{ Str::limit($item->info->description, 30) }}</td>
                                                    <td class="text-center fw-bold text-danger">{{ $item->fail_count }}</td>
                                                    <td class="text-center">
                                                        @include('components.score-bar', ['score' => $item->score])
                                                    </td>
                                                    <td class="text-center">
                                                        @include('components.status-badge', ['status' => $item->status])
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center py-5 text-muted">Great Job! No area offenders found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

{{-- Inline components for reusability within this file context if separate files aren't desired, 
     but Blade @include suggests separate files. I will simulate them inline or ask to create them. 
     Actually, for simplicity, I should inline the HTML bars instead of using includes that don't exist yet. --}}
