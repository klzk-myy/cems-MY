<?php

namespace App\Enums;

enum BranchType: string
{
    case HeadOffice = 'head_office';
    case Branch = 'branch';
    case SubBranch = 'sub_branch';

    public function label(): string
    {
        return match ($this) {
            self::HeadOffice => 'Head Office',
            self::Branch => 'Branch',
            self::SubBranch => 'Sub-Branch',
        };
    }
}
