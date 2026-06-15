<?php

namespace App\Models\Bases;

use App\Models\BaseModel;
use App\Models\Traits\HasTimeScopes;

abstract class SystemModel extends BaseModel
{
    use HasTimeScopes;
}
