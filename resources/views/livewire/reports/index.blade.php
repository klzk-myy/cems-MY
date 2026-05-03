<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Reports</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">BNM compliance and regulatory reports</p>
    </div>
    <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">
        Generate Report
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 flex items-center justify-center bg-blue-100 rounded-lg">
                <span class="text-blue-600">📊</span>
            </div>
            <h3 class="text-sm font-semibold text-[--color-ink]">MSB2 Report</h3>
        </div>
        <p class="text-xs text-[--color-ink-muted] mb-4">Daily transaction summary for Bank Negara Malaysia</p>
        <a href="{{ route('reports.msb2') }}" class="text-sm text-[--color-accent] hover:underline">View Report</a>
    </div>
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 flex items-center justify-center bg-green-100 rounded-lg">
                <span class="text-green-600">💵</span>
            </div>
            <h3 class="text-sm font-semibold text-[--color-ink]">LCTR</h3>
        </div>
        <p class="text-xs text-[--color-ink-muted] mb-4">Large Cash Transaction Report (≥ RM 25,000)</p>
        <a href="{{ route('reports.lctr') }}" class="text-sm text-[--color-accent] hover:underline">View Report</a>
    </div>
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 flex items-center justify-center bg-orange-100 rounded-lg">
                <span class="text-orange-600">🚨</span>
            </div>
            <h3 class="text-sm font-semibold text-[--color-ink]">STR</h3>
        </div>
        <p class="text-xs text-[--color-ink-muted] mb-4">Suspicious Transaction Report for AML/CFT compliance</p>
        <a href="{{ route('str.index') }}" class="text-sm text-[--color-accent] hover:underline">View Reports</a>
    </div>
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 flex items-center justify-center bg-purple-100 rounded-lg">
                <span class="text-purple-600">📈</span>
            </div>
            <h3 class="text-sm font-semibold text-[--color-ink]">CTR</h3>
        </div>
        <p class="text-xs text-[--color-ink-muted] mb-4">Cash Transaction Report for transactions ≥ RM 25,000</p>
        <a href="{{ route('reports.lctr') }}" class="text-sm text-[--color-accent] hover:underline">View Report</a>
    </div>
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 flex items-center justify-center bg-yellow-100 rounded-lg">
                <span class="text-yellow-600">📋</span>
            </div>
            <h3 class="text-sm font-semibold text-[--color-ink]">LMCA</h3>
        </div>
        <p class="text-xs text-[--color-ink-muted] mb-4">Monthly Large Cash Transaction Summary</p>
        <a href="{{ route('reports.lmca') }}" class="text-sm text-[--color-accent] hover:underline">View Report</a>
    </div>
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 flex items-center justify-center bg-red-100 rounded-lg">
                <span class="text-red-600">⚠️</span>
            </div>
            <h3 class="text-sm font-semibold text-[--color-ink]">EDD Reports</h3>
        </div>
        <p class="text-xs text-[--color-ink-muted] mb-4">Enhanced Due Diligence reports for high-risk customers</p>
        <a href="{{ route('compliance.edd.index') }}" class="text-sm text-[--color-accent] hover:underline">View Reports</a>
    </div>
</div>

<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Recent Reports</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Period</th>
                    <th>Generated</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentReports ?? [] as $report)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-medium">{{ $report->report_type }}</td>
                    <td class="text-[--color-ink-muted]">{{ $report->period_start }}</td>
                    <td class="text-[--color-ink-muted]">{{ $report->generated_at->format('d M Y H:i') }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($report->status === 'submitted') bg-green-100 text-green-700
                            @elseif($report->status === 'pending') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ ucfirst($report->status) }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-[--color-ink-muted]">No reports found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>