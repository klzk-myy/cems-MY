<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sanctions Entry</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Create Sanctions Entry</h1>
                    <p class="mt-1 text-sm text-gray-500">Add a new sanctions list entry</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Cancel
                </a>
            </div>
        </div>

        <!-- Form -->
        <form class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Entity Name *</label>
                    <input type="text" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">List Source *</label>
                    <select class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                        <option value="">Select Source</option>
                        <option value="ofac">OFAC SDN</option>
                        <option value="un">UN Security Council</option>
                        <option value="eu">EU Sanctions List</option>
                        <option value="bnm">BNM List</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Entity Type *</label>
                    <select class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                        <option value="">Select Type</option>
                        <option value="individual">Individual</option>
                        <option value="organization">Organization</option>
                        <option value="vessel">Vessel</option>
                        <option value="aircraft">Aircraft</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Reference Number</label>
                    <input type="text" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Nationality</label>
                    <input type="text" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Date Listed</label>
                    <input type="date" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Aliases</label>
                <textarea class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" rows="3" placeholder="Enter aliases, one per line"></textarea>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Address</label>
                <input type="text" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg mb-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <input type="text" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="City">
                    <input type="text" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="Country">
                    <input type="text" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="Postal Code">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Additional Information</label>
                <textarea class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" rows="3"></textarea>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3">
                <button type="button" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Save Entry
                </button>
            </div>
        </form>
    </div>
</body>
</html>