<x-app-layout title="New STR Report">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Create STR Report</h1>

        <form method="POST" action="{{ route('str.store') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                    <select name="customer_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                        @foreach($customers ?? [] as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Suspicious Activity Type</label>
                    <select name="activity_type" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                        <option value="Structuring">Structuring</option>
                        <option value="Sanction_Match">Sanction Match</option>
                        <option value="Velocity">Velocity</option>
                        <option value="Large_Cash">Large Cash Transaction</option>
                        <option value="Unusual_Pattern">Unusual Pattern</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description of Suspicious Activity</label>
                    <textarea name="description" rows="4" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (MYR)</label>
                    <input type="number" step="0.01" name="amount" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filing Deadline</label>
                    <input type="date" name="filing_deadline" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Create STR</button>
                <a href="{{ route('str.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>