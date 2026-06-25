<?php

namespace App\Enums;

enum EddTemplateType: string
{
    case Pep = 'pep';
    case HighRiskCountry = 'high_risk_country';
    case UnusualPattern = 'unusual_pattern';
    case SanctionMatch = 'sanction_match';
    case LargeTransaction = 'large_transaction';
    case HighRiskIndustry = 'high_risk_industry';

    public function label(): string
    {
        return match ($this) {
            self::Pep => 'Politically Exposed Person (PEP)',
            self::HighRiskCountry => 'High-Risk Country',
            self::UnusualPattern => 'Unusual Transaction Pattern',
            self::SanctionMatch => 'Sanctions List Match',
            self::LargeTransaction => 'Large Transaction (>= RM 50,000)',
            self::HighRiskIndustry => 'High-Risk Industry',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pep => 'For customers who are or have connections to politically exposed persons',
            self::HighRiskCountry => 'For customers from countries with elevated AML/CFT risk',
            self::UnusualPattern => 'For customers showing unusual transaction patterns',
            self::SanctionMatch => 'For customers matching sanctions watchlists',
            self::LargeTransaction => 'For transactions >= RM 50,000 requiring enhanced scrutiny',
            self::HighRiskIndustry => 'For customers in high-risk industries (gaming, cash-intensive, etc.)',
        };
    }
}
