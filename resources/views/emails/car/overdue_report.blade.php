<x-mail::message>
# ⚠️ Daily Overdue CAR Report

The following Corrective Action Requests are overdue:

<x-mail::table>
| Issue | Dept | Assigned To | Due Date | Status |
| :--- | :--- | :--- | :--- | :--- |
@foreach($actions as $action)
| {{ $action->log->checkpoint->title ?? '-' }} | {{ $action->log->session->department->dept_name ?? '-' }} | {{ $action->assignee->name ?? 'Unassigned' }} | {{ $action->due_date ? $action->due_date->format('d/m/Y') : '-' }} | {{ $action->due_date ? $action->due_date->diffForHumans() : '-' }} |
@endforeach
</x-mail::table>

Please follow up with the responsible departments.

<x-mail::button :url="route('corrective.index')">
View Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
