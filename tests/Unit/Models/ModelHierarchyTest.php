<?php

namespace Tests\Unit\Models;

use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerNote;
use App\Models\CustomerRelation;
use App\Models\CustomerRiskHistory;
use App\Models\RiskScoreSnapshot;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_models_extend_base_model(): void
    {
        $models = [
            Branch::class,
            Counter::class,
            CounterSession::class,
            CounterHandover::class,
            TillBalance::class,
            TellerAllocation::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }

    public function test_customer_models_extend_base_model(): void
    {
        $models = [
            Customer::class,
            CustomerDocument::class,
            CustomerNote::class,
            CustomerRelation::class,
            CustomerRiskHistory::class,
            RiskScoreSnapshot::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }
}
