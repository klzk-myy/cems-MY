<x-app-layout title="Edit STR Report">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Edit STR Report</h1>

        <form method="POST" action="{{ route('str.update', $str) }}" class="bg-white rounded-lg shadow p-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                    <select name="customer_id" class="w-full border rounded px-3 py-2" required>
                        @foreach($customers ?? [] as $customer)
                            <option value="{{ $customer->id }}" {{ $str->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Suspicious Activity Type</label>
                    <select name="activity_type" class="w-full border rounded px-3 py-2" required>
                        @foreach(['Structuring', 'Sanction_Match', 'Velocity', 'Large_Cash', 'Unusual_Pattern', 'Other'] as $type)
                            <option value="{{ $type }}" {{ $str->activity_type === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description of Suspicious Activity</label>
                    <textarea name="description" rows="4" class="w-full border rounded px-3 py-2" required>{{ $str->description }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (MYR)</label>
                    <input type="number" step="0.01" name="amount" value="{{ $str->amount }}" class="w-full border rounded px-3 py-2" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filing Deadline</label>
                    <input type="date" name="filing_deadline" value="{{ $str->filing_deadline?->format('Y-m-d') }}" class="w-full border rounded px-3 py-2">
                </div>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update STR</button>
                <a href="{{ route('str.show', $str) }}" class="px-6 py-2 border rounded hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>