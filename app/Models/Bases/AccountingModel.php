<?php

namespace App\Models\Bases;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToBranch;

abstract class AccountingModel extends BaseModel
{
    use BelongsToBranch;
}
