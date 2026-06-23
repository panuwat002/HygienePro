<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Inspection Compliance Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Filter -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 flex items-center justify-between">
                    <form action="{{ route('compliance.dashboard') }}" method="GET" class="flex items-center space-x-4">
                        <label for="date" class="font-medium text-gray-700">Select Date:</label>
                        <input type="date" name="date" value="{{ $date }}" class="form-input rounded-md shadow-sm" onchange="this.form.submit()">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Refresh</button>
                    </form>

                    <div class="flex space-x-4">
                        <div class="text-center">
                            <span class="block text-2xl font-bold text-gray-700">{{ $stats['total'] }}</span>
                            <span class="text-xs text-gray-500 uppercase">Total Scheduled</span>
                        </div>
                        <div class="text-center">
                            <span class="block text-2xl font-bold text-green-600">{{ $stats['completed'] }}</span>
                            <span class="text-xs text-gray-500 uppercase">Completed</span>
                        </div>
                         <div class="text-center">
                            <span class="block text-2xl font-bold text-yellow-500">{{ $stats['pending'] }}</span>
                            <span class="text-xs text-gray-500 uppercase">Pending</span>
                        </div>
                        <div class="text-center">
                            <span class="block text-2xl font-bold text-red-600">{{ $stats['missed'] }}</span>
                            <span class="text-xs text-gray-500 uppercase">Missed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Window</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            </tr>
                        </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($complianceData as $item)
                                <tr class="{{ $item['status'] === 'missed' ? 'bg-red-50' : ($item['status'] === 'completed' ? 'bg-green-50' : '') }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($item['status'] === 'completed')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Completed
                                            </span>
                                        @elseif($item['status'] === 'missed')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Missed
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                        {{ $item['schedule']->title }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                        {{ class_basename($item['schedule']->targetable_type) }}: {{ $item['schedule']->targetable->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                        {{ $item['window'] }}
                                    </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                        {{ $item['schedule']->department->name ?? '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        No inspections scheduled for this date.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
