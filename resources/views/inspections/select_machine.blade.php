<x-app-layout>
    @section('header', 'เลือกเครื่องจักร / พื้นที่ย่อย')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold text-primary mb-1">{{ $location->location_name }}</h5>
                    <p class="text-muted">กรุณาเลือกเครื่องจักรหรือจุดที่ต้องการตรวจ</p>
                </div>
            </div>

            <div class="row g-3">
                @foreach($machines as $machine)
                <div class="col-md-6">
                    <a href="{{ route('inspection.area.checklist', ['department' => $department->id, 'location' => $location->id, 'machine_id' => $machine->id]) }}" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm rounded-4 hover-shadow transition">
                            <div class="card-body p-4 d-flex align-items-center">
                                <div class="rounded-3 bg-light d-flex justify-content-center align-items-center border overflow-hidden me-3" style="width: 60px; height: 60px;">
                                    @if($machine->image)
                                        <img src="{{ asset('storage/' . $machine->image) }}" class="w-100 h-100 object-fit-cover" alt="Machine">
                                    @else
                                        <i class="bi bi-gear-wide-connected text-secondary fs-4"></i>
                                    @endif
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">{{ $machine->name }}</h6>
                                    <small class="text-muted d-block">{{ $machine->code ? 'Code: '.$machine->code : 'No Code' }}</small>
                                </div>
                                <div class="ms-auto">
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
            
            <div class="text-center mt-4">
                 <a href="{{ route('inspection.dashboard', 'area') }}" class="btn btn-light rounded-pill px-4">
                    <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
