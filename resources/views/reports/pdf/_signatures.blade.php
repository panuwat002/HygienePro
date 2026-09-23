<div class="remarks">
    <strong>หมายเหตุ:</strong> 
    เครื่องหมาย <span class="text-success">/</span> หมายถึง ผ่านการตรวจสอบ (สำหรับช่วงที่ 1 และ 2) เรียบร้อย, ไม่มีการเจ็บป่วย &nbsp;&nbsp;
    เครื่องหมาย <span class="text-danger">X</span> หมายถึง ไม่ผ่านการตรวจสอบ (สำหรับช่วงที่ 1 และ 2) ไม่เรียบร้อย และบันทึกการแก้ไข &nbsp;&nbsp;
    เครื่องหมาย - หมายถึง ไม่เกี่ยวข้อง
</div>

@php
    // Robust date/time resolution (use passed controller variables or derive from sessions/logs)
    $recAt = isset($recordedAt) && $recordedAt 
        ? \Carbon\Carbon::parse($recordedAt) 
        : ($sessions->pluck('created_at')->filter()->max() ?? $sessions->flatMap(fn($s) => $s->logs)->max('inspected_at'));
    $recAt = $recAt ? \Carbon\Carbon::parse($recAt) : null;

    $verAt = isset($verifiedAt) && $verifiedAt 
        ? \Carbon\Carbon::parse($verifiedAt) 
        : ($sessions->pluck('verified_at')->filter()->max() ?? $sessions->flatMap(fn($s) => $s->logs)->whereNotNull('verified_at')->max('verified_at'));
    $verAt = $verAt ? \Carbon\Carbon::parse($verAt) : null;

    $appAt = isset($approvedAt) && $approvedAt 
        ? \Carbon\Carbon::parse($approvedAt) 
        : ($sessions->pluck('approved_at')->filter()->max() ?? $sessions->flatMap(fn($s) => $s->logs)->where('verification_status', 'approved')->max('verified_at'));
    $appAt = $appAt ? \Carbon\Carbon::parse($appAt) : null;

    // Primary inspector
    $firstInspector = $sessions->first()?->inspector;
@endphp

<div class="signatures">
    <!-- 1. ผู้บันทึก (Recorder) -->
    <div class="signature-box">
        <div style="font-size: 10px; font-weight: bold; margin-bottom: 4px;">ผู้บันทึก (Recorder)</div>
        
        <div style="height: 32px; text-align: center; line-height: 1.2;">
            @if($firstInspector)
                @if($firstInspector->signature_path && (file_exists(storage_path('app/public/' . $firstInspector->signature_path)) || file_exists(public_path('storage/' . $firstInspector->signature_path))))
                    @php 
                        $sigPath = storage_path('app/public/' . $firstInspector->signature_path);
                        if(!file_exists($sigPath)) {
                            $sigPath = public_path('storage/' . $firstInspector->signature_path);
                        }
                    @endphp
                    <img src="{{ $sigPath }}" style="max-height: 18px;"><br>
                    @if($recAt)
                        <span style="font-size: 8px; color: #555;">{{ $recAt->format('d/m/Y H:i') }}</span>
                    @endif
                @else
                    <span style="font-size: 11px; font-weight: bold; color: #333;">{{ $firstInspector->name }}</span><br>
                    <span style="font-size: 8px; color: #666;">(Digital Record)@if($recAt) {{ $recAt->format('d/m/Y H:i') }}@endif</span>
                @endif
            @endif
        </div>
        
        <div style="border-bottom: 1px dotted #000; width: 85%; margin: 4px auto;"></div>
        
        <div style="font-size: 9px; line-height: 1.2;">( {{ $firstInspector->name ?? '............................................' }} )</div>
        <div style="margin-top: 2px; font-size: 9px;">พนักงานประกันคุณภาพ</div>
    </div>
    
    <!-- 2. ผู้ทวนสอบ (Verified By) -->
    <div class="signature-box">
        <div style="font-size: 10px; font-weight: bold; margin-bottom: 4px;">ผู้ทวนสอบ (Verified By)</div>
        
        <div style="height: 32px; text-align: center; line-height: 1.2;">
            @if($verifiers->isNotEmpty() || $verAt)
                @php $firstVerifier = $verifiers->first(); @endphp
                @if($firstVerifier && $firstVerifier->signature_path && (file_exists(storage_path('app/public/' . $firstVerifier->signature_path)) || file_exists(public_path('storage/' . $firstVerifier->signature_path))))
                    @php 
                        $sigPath2 = storage_path('app/public/' . $firstVerifier->signature_path);
                        if(!file_exists($sigPath2)) {
                            $sigPath2 = public_path('storage/' . $firstVerifier->signature_path);
                        }
                    @endphp
                    <img src="{{ $sigPath2 }}" style="max-height: 18px;"><br>
                    @if($verAt)
                        <span style="font-size: 8px; color: #555;">{{ $verAt->format('d/m/Y H:i') }}</span>
                    @endif
                @else
                    <span style="font-size: 11px; font-weight: bold; color: #198754;">{{ $firstVerifier->name ?? 'Verified' }}</span><br>
                    <span style="font-size: 8px; color: #198754;">(Digital Verified)@if($verAt) {{ $verAt->format('d/m/Y H:i') }}@endif</span>
                @endif
            @endif
        </div>
        
        <div style="border-bottom: 1px dotted #000; width: 85%; margin: 4px auto;"></div>
        
        <div style="font-size: 9px; line-height: 1.2;">( {{ $verifiers->isNotEmpty() ? $verifiers->pluck('name')->join(', ') : '............................................' }} )</div>
        <div style="margin-top: 2px; font-size: 9px;">QA Supervisor</div>
    </div>

    <!-- 3. ผู้อนุมัติ (Approved By) -->
    <div class="signature-box">
        <div style="font-size: 10px; font-weight: bold; margin-bottom: 4px;">ผู้อนุมัติ (Approved By)</div>
        
        <div style="height: 32px; text-align: center; line-height: 1.2;">
            @if($approvers->isNotEmpty() || $appAt)
                @php $firstApprover = $approvers->first(); @endphp
                @if($firstApprover && $firstApprover->signature_path && (file_exists(storage_path('app/public/' . $firstApprover->signature_path)) || file_exists(public_path('storage/' . $firstApprover->signature_path))))
                    @php 
                        $sigPath3 = storage_path('app/public/' . $firstApprover->signature_path);
                        if(!file_exists($sigPath3)) {
                            $sigPath3 = public_path('storage/' . $firstApprover->signature_path);
                        }
                    @endphp
                    <img src="{{ $sigPath3 }}" style="max-height: 18px;"><br>
                    @if($appAt)
                        <span style="font-size: 8px; color: #555;">{{ $appAt->format('d/m/Y H:i') }}</span>
                    @endif
                @else
                    <span style="font-size: 11px; font-weight: bold; color: #0d6efd;">{{ $firstApprover->name ?? 'Approved' }}</span><br>
                    <span style="font-size: 8px; color: #0d6efd;">(Digital Approved)@if($appAt) {{ $appAt->format('d/m/Y H:i') }}@endif</span>
                @endif
            @endif
        </div>
        
        <div style="border-bottom: 1px dotted #000; width: 85%; margin: 4px auto;"></div>
        
        <div style="font-size: 9px; line-height: 1.2;">( {{ $approvers->isNotEmpty() ? $approvers->pluck('name')->join(', ') : '............................................' }} )</div>
        <div style="margin-top: 2px; font-size: 9px;">QA Manager</div>
    </div>

    <div style="clear: both;"></div>
</div>
