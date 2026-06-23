<div class="remarks">
    <strong>หมายเหตุ:</strong> 
    เครื่องหมาย <span class="text-success">/</span> หมายถึง ผ่านการตรวจสอบ (สำหรับช่วงที่ 1 และ 2) เรียบร้อย, ไม่มีการเจ็บป่วย &nbsp;&nbsp;
    เครื่องหมาย <span class="text-danger">X</span> หมายถึง ไม่ผ่านการตรวจสอบ (สำหรับช่วงที่ 1 และ 2) ไม่เรียบร้อย และบันทึกการแก้ไข &nbsp;&nbsp;
    เครื่องหมาย - หมายถึง ไม่เกี่ยวข้อง
</div>

<div class="signatures">
    <div class="signature-box">
        <div>ผู้บันทึก (Recorder)</div>
        <div class="signature-line" style="border-bottom: none; height: 30px; position: relative;">
            @if($sessions->first() && $sessions->first()->inspector)
                 <div style="position: absolute; bottom: 0; width: 100%; font-family: 'Courier New', monospace; color: #555;">
                    {{ $sessions->first()->inspector->name }}<br>
                    <span style="font-size: 8px;">(Digital Record)</span>
                 </div>
            @endif
            <div style="border-bottom: 1px dotted #000; width: 100%; position: absolute; bottom: 0;"></div>
        </div>
        <div>( {{ $sessions->first()->inspector->name ?? '............................................' }} )</div>
        <div style="margin-top: 5px;">พนักงานประกันคุณภาพ</div>
    </div>
    
    <div class="signature-box">
        <div>ผู้ทวนสอบ (Verified By)</div>
        <div class="signature-line" style="border-bottom: none; height: 30px; position: relative;">
             @if($verifiers->isNotEmpty())
                 <div style="position: absolute; bottom: 0; width: 100%; color: #28a745; font-weight: bold;">
                    <span style="font-family: 'Courier New';">Verified: {{ $sessions->first()->verified_at ? $sessions->first()->verified_at->format('d/m/Y H:i') : '' }}</span>
                 </div>
            @endif
            <div style="border-bottom: 1px dotted #000; width: 100%; position: absolute; bottom: 0;"></div>
        </div>
        <div>( {{ $verifiers->isNotEmpty() ? $verifiers->pluck('name')->join(', ') : '............................................' }} )</div>
        <div style="margin-top: 5px;">QA Supervisor</div>
    </div>

    <div class="signature-box">
        <div>ผู้อนุมัติ (Approved By)</div>
         <div class="signature-line" style="border-bottom: none; height: 30px; position: relative;">
             @if($approvers->isNotEmpty())
                 <div style="position: absolute; bottom: 0; width: 100%; color: #0d6efd; font-weight: bold;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e4/A_check_mark.png/600px-A_check_mark.png" style="height: 15px; opacity: 0.5;">
                    <span style="font-family: 'Courier New';">Approved: {{ $sessions->first()->approved_at ? $sessions->first()->approved_at->format('d/m/Y H:i') : '' }}</span>
                 </div>
            @endif
            <div style="border-bottom: 1px dotted #000; width: 100%; position: absolute; bottom: 0;"></div>
        </div>
        <div>( {{ $approvers->isNotEmpty() ? $approvers->pluck('name')->join(', ') : '............................................' }} )</div>
        <div style="margin-top: 5px;">QA Manager</div>
    </div>
</div>
