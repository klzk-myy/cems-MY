<?php

namespace App\Models\Bases;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToBranch;
use App\Models\Traits\BelongsToCurrency;
use App\Models\Traits\BelongsToCustomer;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\HasApprover;
use App\Models\Traits\HasStatus;
use Illuminate\Support\Carbon;

/**
 * Base class for all transaction-like models sharing branch/currency/customer/user/approver relations.
 *
 * @property int|null $branch_id
 * @property string $currency_code
 * @property int $customer_id
 * @property int $user_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
abstract class TransactionModel extends BaseModel
{
    use BelongsToBranch,
        BelongsToCurrency,
        BelongsToCustomer,
        BelongsToUser,
        HasApprover,
        HasStatus;
}
