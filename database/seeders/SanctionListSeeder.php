<?php

namespace Database\Seeders;

use App\Models\SanctionEntry;
use App\Models\SanctionList;
use Illuminate\Database\Seeder;

class SanctionListSeeder extends Seeder
{
    public function run(): void
    {
        $sources = config('sanctions.sources');
        $adminUser = \App\Models\User::where('role', 'admin')->first();
        $uploadedBy = $adminUser?->id;

        foreach ($sources as $key => $source) {
            $list = SanctionList::updateOrCreate(
                ['slug' => $key],
                [
                    'name' => $source['name'],
                    'source_url' => $source['url'],
                    'source_format' => $source['format'],
                    'list_type' => $source['list_type'] === 'international' ? 'UNSCR' : 'MOHA',
                    'is_active' => $source['default_list'] ?? false,
                    'uploaded_by' => $uploadedBy,
                ]
            );

            $this->seedEntriesForList($list, $key);
        }

        $totalLists = count($sources);
        $this->command->info("Seeded {$totalLists} sanction lists from configuration");
    }

    protected function seedEntriesForList(SanctionList $list, string $listKey): void
    {
        $entries = $this->getDemoEntries($listKey);

        foreach ($entries as $entry) {
            SanctionEntry::updateOrCreate(
                ['list_id' => $list->id, 'entity_name' => $entry['entity_name']],
                $entry
            );
        }
    }

    protected function getDemoEntries(string $listKey): array
    {
        return match ($listKey) {
            'un_consolidated' => [
                [
                    'entity_name' => 'John Doe Ali',
                    'entity_type' => 'Individual',
                    'aliases' => json_encode(['Johan Doe', 'J. Doe']),
                    'nationality' => 'IR',
                    'date_of_birth' => '1975-03-15',
                    'details' => json_encode(['address' => 'Tehran, Iran', 'passport' => 'X1234567']),
                ],
                [
                    'entity_name' => 'Kim Jong-un',
                    'entity_type' => 'Individual',
                    'aliases' => json_encode(['Kim Jong-un', 'Kim Jong Un']),
                    'nationality' => 'KP',
                    'date_of_birth' => '1984-01-08',
                    'details' => json_encode(['address' => 'Pyongyang, DPRK']),
                ],
            ],
            'moha_malaysia' => [
                [
                    'entity_name' => 'Ahmad bin Mahmud',
                    'entity_type' => 'Individual',
                    'aliases' => json_encode(['Ahmad M.', 'Abu Mahmud']),
                    'nationality' => 'MY',
                    'date_of_birth' => '1980-07-22',
                    'details' => json_encode(['ic_no' => '700101-14-1234']),
                ],
            ],
            default => [],
        };
    }
}
