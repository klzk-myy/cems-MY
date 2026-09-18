<x-app-layout title="Branch & HQ Scope">
    <div class="space-y-6">
        <x-page-header title="Branch & HQ Scope" description="The operating boundary between standalone branches and the HQ administrative office. Read-only — actual grants come from role permissions and branch assignment." />

        <x-card>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                            <th class="px-4 py-3 text-left">Domain</th>
                            @foreach ($columns as $column)
                                <th class="px-4 py-3 text-center">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-border hover:bg-canvas-subtle">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-ink">{{ $row['domain'] }}</div>
                                    <div class="text-xs text-ink-muted mt-0.5">{{ $row['description'] }}</div>
                                </td>
                                @foreach ($columns as $key => $column)
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-sm {{ $row[$key] === '—' ? 'text-ink-muted' : 'text-ink' }}">{{ $row[$key] }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>
