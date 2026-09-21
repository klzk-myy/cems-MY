<?php

namespace Database\Seeders;

use App\Enums\SanctionListType;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionList;
use App\Models\User;
use Illuminate\Database\Seeder;

class SanctionListSeeder extends Seeder
{
    public function run(): void
    {
        $sources = config('sanctions.sources');
        $adminUser = User::where('role', 'admin')->first();
        $uploadedBy = $adminUser?->id;

        foreach ($sources as $key => $source) {
            $list = SanctionList::updateOrCreate(
                ['slug' => $key],
                [
                    'name' => $source['name'],
                    'source_url' => $source['url'],
                    'source_format' => $source['format'],
                    'list_type' => match ($source['list_type'] ?? 'national') {
                        'international' => SanctionListType::UNSCR,
                        'domestic_alert' => SanctionListType::Domestic,
                        'internal' => SanctionListType::Internal,
                        default => SanctionListType::MOHA,
                    },
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
            $normalizedName = $this->normalizeName($entry['entity_name']);
            $entry['normalized_name'] = $normalizedName;
            $entry['soundex_code'] = soundex($normalizedName);
            $entry['metaphone_code'] = metaphone($normalizedName);

            SanctionEntry::updateOrCreate(
                ['list_id' => $list->id, 'entity_name' => $entry['entity_name']],
                $entry
            );
        }
    }

    protected function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}\s\-\'\.]/u', '', $name);

        return trim($name);
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
