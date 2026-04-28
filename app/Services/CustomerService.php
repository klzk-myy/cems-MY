<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SystemLog;
use App\Services\Compliance\RiskScoringEngine;
use Illuminate\Support\Facades\DB;

/**
 * Customer Service
 *
 * Handles all customer-related business logic including:
 * - Customer creation and updates
 * - Encryption of sensitive data
 * - Sanctions screening
 * - Risk assessment
 * - PEP and high-risk determination
 * - Blind index operations
 *
 * This service removes business logic from controllers and models,
 * ensuring proper MVC separation of concerns.
 */
class CustomerService
{
    public function __construct(
        protected EncryptionService $encryptionService,
        protected CustomerScreeningService $screeningService,
        protected RiskScoringEngine $riskScoringEngine,
        protected AuditService $auditService,
        protected CacheTagsService $cacheTagsService
    ) {}

    /**
     * Create a new customer with encryption, screening, and risk assessment.
     * Initial risk_rating is always 'Low' - automated risk scoring module determines actual risk.
     *
     * @param  array  $data  Customer data
     * @param  int  $userId  User ID creating the customer
     * @return Customer Created customer
     */
    public function createCustomer(array $data, int $userId): Customer
    {
        $customer = DB::transaction(function () use ($data) {
            // Encrypt sensitive fields
            $encryptedData = $this->encryptCustomerData($data);

            // Initial risk always 'Low' - risk scoring module determines actual risk
            $encryptedData['risk_rating'] = 'Low';

            // Create customer
            $customer = Customer::create($encryptedData);

            // Screen against sanctions list (may upgrade to High if hit)
            $this->screenCustomer($customer, $data['full_name']);

            // Calculate risk score using automated risk scoring engine
            $this->calculateRiskScore($customer);

            // Log customer creation
            $this->auditService->logCustomer('customer_created', $customer->id, [
                'new' => [
                    'full_name' => $customer->full_name,
                    'id_type' => $customer->id_type,
                    'nationality' => $customer->nationality,
                    'risk_rating' => $customer->risk_rating,
                    'pep_status' => $customer->pep_status,
                    'sanction_hit' => $customer->sanction_hit,
                ],
            ]);

            return $customer;
        });
        $this->cacheTagsService->invalidate('dashboard');

        return $customer;
    }

    /**
     * Update an existing customer with encryption and risk reassessment.
     *
     * @param  Customer  $customer  Customer to update
     * @param  array  $data  Updated customer data
     * @param  int  $userId  User ID updating the customer
     * @return Customer Updated customer
     */
    public function updateCustomer(Customer $customer, array $data, int $userId): Customer
    {
        $customer = DB::transaction(function () use ($customer, $data) {
            // Encrypt sensitive fields if provided
            $encryptedData = $this->encryptCustomerData($data);

            // Update customer
            $customer->update($encryptedData);

            // Re-screen against sanctions if name changed
            if (isset($data['full_name']) && $data['full_name'] !== $customer->full_name) {
                $this->screenCustomer($customer, $data['full_name']);
            }

            // Recalculate risk score
            $this->calculateRiskScore($customer);

            // Log customer update
            $this->auditService->logCustomer('customer_updated', $customer->id, [
                'old' => [
                    'full_name' => $customer->getOriginal('full_name'),
                    'risk_rating' => $customer->getOriginal('risk_rating'),
                ],
                'new' => [
                    'full_name' => $customer->full_name,
                    'risk_rating' => $customer->risk_rating,
                ],
            ]);

            return $customer->fresh();
        });
        $this->cacheTagsService->invalidate('dashboard');

        return $customer;
    }

    /**
     * Determine if a customer is a PEP associate.
     *
     * A customer is a PEP associate if they have any PEP relations.
     *
     * @param  Customer  $customer  Customer to check
     * @return bool True if customer is a PEP associate
     */
    public function isPepAssociate(Customer $customer): bool
    {
        return $customer->pepRelations()->where('is_pep', true)->exists();
    }

    /**
     * Determine if a customer is high risk.
     *
     * A customer is high risk if their risk rating is 'High',
     * they are a PEP, or they have a sanctions match.
     *
     * @param  Customer  $customer  Customer to check
     * @return bool True if customer is high risk
     */
    public function isHighRisk(Customer $customer): bool
    {
        return $customer->risk_rating === 'High'
            || $customer->pep_status
            || $customer->sanction_hit;
    }

    /**
     * Compute a deterministic HMAC hash of the ID number for blind indexing.
     *
     * Blind indexing allows exact-match searches on encrypted fields
     * without decrypting the data.
     *
     * @param  string  $plaintext  Plaintext ID number
     * @return string HMAC-SHA256 hash
     */
    public static function computeBlindIndex(string $plaintext): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $plaintext, $key);
    }

    /**
     * Find a customer by their ID number using the blind index.
     *
     * This allows searching for customers by ID number without
     * decrypting the encrypted field.
     *
     * @param  string  $idNumber  ID number to search for
     * @return Customer|null Customer if found, null otherwise
     */
    public function findByIdNumber(string $idNumber): ?Customer
    {
        $hash = $this->computeBlindIndex($idNumber);

        return Customer::where('id_number_hash', $hash)->first();
    }

    /**
     * Encrypt customer sensitive data.
     *
     * @param  array  $data  Customer data
     * @return array Encrypted customer data
     */
    protected function encryptCustomerData(array $data): array
    {
        $encrypted = $data;

        // Encrypt ID number
        if (isset($data['id_number'])) {
            $encrypted['id_number_encrypted'] = $this->encryptionService->encrypt($data['id_number']);
            unset($encrypted['id_number']);
        }

        // Encrypt address
        if (! empty($data['address'])) {
            $encrypted['address'] = $this->encryptionService->encrypt($data['address']);
        }

        // Encrypt phone
        if (! empty($data['phone'])) {
            $encrypted['phone'] = $this->encryptionService->encrypt($data['phone']);
        }

        // Encrypt employer address
        if (! empty($data['employer_address'])) {
            $encrypted['employer_address'] = $this->encryptionService->encrypt($data['employer_address']);
        }

        return $encrypted;
    }

    /**
     * Screen a customer against sanctions lists.
     *
     * @param  Customer  $customer  Customer to screen
     * @param  string  $fullName  Full name to screen
     */
    protected function screenCustomer(Customer $customer, string $fullName): void
    {
        $sanctionMatches = $this->screeningService->screenName($fullName);
        $hasSanctionHit = ! empty($sanctionMatches);

        // Update sanction status, risk rating, AND deactivate if hit found
        if ($hasSanctionHit) {
            $customer->update([
                'risk_rating' => 'High',
                'sanction_hit' => true,
                'is_active' => false, // Require Manager/Compliance approval to activate
            ]);

            // Log sanction hit
            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'customer_sanction_hit',
                'severity' => 'WARNING',
                'entity_type' => 'Customer',
                'entity_id' => $customer->id,
                'new_values' => [
                    'customer_name' => $customer->full_name,
                    'sanction_matches' => $sanctionMatches,
                ],
                'ip_address' => request()->ip(),
            ]);
        }
    }

    /**
     * Calculate risk score for a customer.
     *
     * @param  Customer  $customer  Customer to assess
     */
    protected function calculateRiskScore(Customer $customer): void
    {
        $this->riskScoringEngine->recalculateForCustomer($customer->id);
    }
}
