<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    // Intentionally thin. Only universally shared defaults (e.g. date format,
    // future UUID/ULID handling) belong here. Business logic lives in traits.
}
