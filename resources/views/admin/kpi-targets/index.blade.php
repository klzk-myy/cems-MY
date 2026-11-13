<x-app-layout title="KPI Targets">
    <div class="space-y-6">
        <x-page-header
            title="KPI Targets"
            description="Operational targets for volume, turnaround and compliance KPIs. A blank cell leaves that metric untargeted."
        />

        <x-card title="Targets by Metric" description="The company-wide column applies to every branch unless a branch cell overrides it.">
            <form method="POST" action="{{ route('admin.kpi-targets.update') }}">
                @csrf

                <x-table>
                    <x-slot:thead>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Metric</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">All Branches</th>
                        @foreach($branches as $branch)
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">{{ $branch->name }}</th>
                        @endforeach
                    </x-slot:thead>
                    <x-slot:tbody>
                    @foreach($metrics as $metric)
                        @foreach([\App\Models\Bi\KpiTarget::PERIOD_DAY, \App\Models\Bi\KpiTarget::PERIOD_MONTH] as $period)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $metric->label() }}</td>
                                <td class="px-4 py-3 text-sm text-ink-muted dark:text-ink-dark">{{ $period }}</td>

                                @php($companyKey = $metric->value.'|all|'.$period)
                                <td class="px-4 py-3 text-sm text-right">
                                    <x-input
                                        name="targets[{{ $companyKey }}]"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        inline
                                        :value="old('targets.'.$companyKey, $targets->get($companyKey)?->target_value)"
                                    />
                                </td>

                                @foreach($branches as $branch)
                                    @php($branchKey = $metric->value.'|'.$branch->id.'|'.$period)
                                    <td class="px-4 py-3 text-sm text-right">
                                        <x-input
                                            name="targets[{{ $branchKey }}]"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            inline
                                            :value="old('targets.'.$branchKey, $targets->get($branchKey)?->target_value)"
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                    </x-slot:tbody>
                </x-table>

                <div class="mt-4 flex justify-end">
                    <x-button variant="primary" type="submit">Save Targets</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
