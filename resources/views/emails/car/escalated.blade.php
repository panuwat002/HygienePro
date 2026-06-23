<x-mail::message>
# 🚨 New Corrective Action Required

Please attend to the following issue immediately.

**Issue:** {{ $action->log->checkpoint->title ?? 'N/A' }}  
**Location:** {{ $action->log->session->department->dept_name ?? 'N/A' }}  
**Reported By:** {{ $action->escalator->name ?? 'QA Team' }}  
**Due By:** <span style="color: #d9534f; font-weight: bold;">{{ $action->due_date ? $action->due_date->format('d/m/Y H:i') : 'ASAP' }}</span>  
**Details:** {{ $action->root_cause ?? '-' }}

@if($action->log->photo_path)
<div style="text-align: center; margin: 20px 0;">
    <img src="{{ asset('storage/' . $action->log->photo_path) }}" alt="Evidence" style="max-width: 100%; border-radius: 8px;">
</div>
@endif

<x-mail::button :url="route('corrective.index')">
View & Resolve
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
