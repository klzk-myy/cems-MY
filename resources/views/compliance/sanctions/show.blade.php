<x-app-layout title="Sanctions">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sanctions Screening Details</h1>
                    <p class="mt-1 text-sm text-gray-500">Screening ID: SCR-2024-001</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Screening Summary -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Screening Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Customer Name</label>
                    <p class="text-sm text-gray-900">Ahmad Razali</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">ID Number</label>
                    <p class="text-sm text-gray-900">IC-12345678</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Screened At</label>
                    <p class="text-sm text-gray-900">2024-01-15 14:30:00</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Result</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Match Found</span>
                </div>
            </div>
        </div>

        <!-- Match Details -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Match Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900">Matched Entity</h4>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">92% Match</span>
                    </div>
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs text-gray-500">Name</label>
                            <p class="text-sm text-gray-900">John Doe</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Type</label>
                            <p class="text-sm text-gray-900">Individual</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">List</label>
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">OFAC SDN</span>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Reference</label>
                            <p class="text-sm text-gray-900">OFAC-12345</p>
                        </div>
                    </div>
                </div>

                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">Match Analysis</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Name Similarity</span>
                            <span class="text-sm font-medium text-green-600">92%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Nationality</span>
                            <span class="text-sm font-medium text-yellow-600">Partial</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">DOB Match</span>
                            <span class="text-sm font-medium text-green-600">Yes</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">ID Number</span>
                            <span class="text-sm font-medium text-gray-600">Not Available</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Decision -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Review Decision</h3>
            <div class="space-y-3">
                <label class="flex items-center gap-3">
                    <input type="radio" name="decision" class="w-4 h-4 text-blue-600">
                    <span class="text-sm text-gray-900">False Positive - Clear Customer</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="radio" name="decision" class="w-4 h-4 text-blue-600">
                    <span class="text-sm text-gray-900">Confirmed Match - Escalate to Compliance</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="radio" name="decision" class="w-4 h-4 text-blue-600">
                    <span class="text-sm text-gray-900">Request Additional Documentation</span>
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Notes</label>
                <textarea class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" rows="3" placeholder="Add review notes..."></textarea>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">
                    Confirm Decision
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Request Manual Review
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    View Customer Profile
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Print Report
                </button>
            </div>
        </div>
    </div>
</x-app-layout>