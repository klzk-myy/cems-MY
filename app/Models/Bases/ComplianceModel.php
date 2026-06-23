<?php

namespace App\Models\Bases;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToCustomer;
use App\Models\Traits\HasStatus;

abstract class ComplianceModel extends BaseModel
{
    use BelongsToCustomer,
        HasStatus;
}
