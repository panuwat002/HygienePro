<div class="employee-list" style="max-height: 450px; overflow-y: auto;">
    @foreach($employees as $employee)
    <div class="form-check mb-2 p-2 rounded border bg-light employee-item" 
         data-name="{{ strtolower($employee->fullname ?? $employee->fname . ' ' . $employee->lname) }}" 
         data-dept="{{ $employee->department_id }}">
        
        <input class="form-check-input employee-checkbox" type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" id="emp_{{ $employee->id }}">
        <label class="form-check-label d-flex align-items-center gap-2 flex-wrap" for="emp_{{ $employee->id }}">
            <span class="fw-medium">{{ $employee->fullname ?? $employee->fname . ' ' . $employee->lname }}</span>
            <small class="text-muted">({{ $employee->employee_id }})</small>
            <span class="badge bg-secondary bg-opacity-25 text-dark small">{{ $employee->department->dept_name ?? '' }}</span>
            @if($employee->checkpoints->count() > 0)
                <span class="badge bg-success bg-opacity-25 text-success small">✔️ {{ $employee->checkpoints->count() }} จุดตรวจ</span>
            @endif
        </label>
    </div>
    @endforeach

    @if($employees->isEmpty())
        <div class="text-center py-4 text-muted">
            <i class="bi bi-search display-6 mb-2 d-block"></i>
            ไม่พบข้อมูลพนักงาน
        </div>
    @endif
</div>

<div class="d-flex justify-content-center mt-3" id="pagination-links">
    {{ $employees->links() }}
</div>
