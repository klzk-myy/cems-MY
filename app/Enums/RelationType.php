<?php

namespace App\Enums;

enum RelationType: string
{
    case Spouse = 'spouse';
    case Child = 'child';
    case Parent = 'parent';
    case Sibling = 'sibling';
    case CloseAssociate = 'close_associate';
    case BusinessPartner = 'business_partner';
    case BeneficialOwner = 'beneficial_owner';
    case Director = 'director';
    case Signatory = 'signatory';
    case RelatedEntity = 'related_entity';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'Spouse',
            self::Child => 'Child',
            self::Parent => 'Parent',
            self::Sibling => 'Sibling',
            self::CloseAssociate => 'Close Associate',
            self::BusinessPartner => 'Business Partner',
            self::BeneficialOwner => 'Beneficial Owner',
            self::Director => 'Director',
            self::Signatory => 'Signatory',
            self::RelatedEntity => 'Related Entity',
        };
    }

    public function isFamily(): bool
    {
        return in_array($this, [self::Spouse, self::Child, self::Parent, self::Sibling]);
    }

    public function isBusiness(): bool
    {
        return in_array($this, [self::BusinessPartner, self::Director, self::Signatory, self::RelatedEntity]);
    }
}
