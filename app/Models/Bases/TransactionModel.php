<?php

namespace App\Models\Bases;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToBranch;
use App\Models\Traits\BelongsToCurrency;
use App\Models\Traits\BelongsToCustomer;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\HasApprover;
use App\Models\Traits\HasStatus;

abstract class TransactionModel extends BaseModel
{
    use BelongsToBranch,
        BelongsToCurrency,
        BelongsToCustomer,
        BelongsToUser,
        HasApprover,
        HasStatus;
}
