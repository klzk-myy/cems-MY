<?php

namespace Tests\Unit\Enums;

use App\Enums\HighRiskCountryRiskLevel;
use App\Models\HighRiskCountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HighRiskCountryRiskLevelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function values_match_database_enum(): void
    {
        $this->assertSame('High', HighRiskCountryRiskLevel::High->value);
        $this->assertSame('Grey', HighRiskCountryRiskLevel::Grey->value);
    }

    #[Test]
    public function model_casts_risk_level_to_enum(): void
    {
        $country = HighRiskCountry::factory()->create([
            'country_code' => 'XX',
            'risk_level' => 'High',
        ]);

        $this->assertInstanceOf(HighRiskCountryRiskLevel::class, $country->risk_level);
        $this->assertTrue($country->risk_level->isHigh());
    }
}
