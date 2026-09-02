<?php

namespace Tests\Http\Simulation\Support;

/**
 * SimulationState — per-scenario references shared across wave steps.
 *
 * Carries the seeded identity graph (branch / counter / user ids, the
 * teller's Sanctum token) plus arbitrary step-created ids so later steps
 * can assert on state produced earlier in the sweep.
 */
class SimulationState
{
    public int $branchId;       // HQ branch

    public int $counterId;      // open counter

    public int $tellerId;       // teller user

    public int $managerId;      // manager user

    public int $complianceId;   // compliance officer user

    public int $adminId;        // admin user

    public int $customerId;     // customer used for transactions

    /** Sanctum token for teller (plain text, stored once). */
    public string $tellerToken;

    public ?int $transactionId = null;  // last booked tx

    /** @var list<string> */
    public array $created = []; // arbitrary step-created ids
}
