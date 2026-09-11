<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * KycSteps — Wave A step A26.
 *
 * A26: KYC document upload on the API surface.
 */
trait KycSteps
{
    /**
     * A26 — API: KYC document upload.
     */
    protected function itManagesKyc(): void
    {
        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/customers/'.$this->state->customerId.'/kyc', [
            'document_type' => 'MyKad',
            'document_number' => '900101-01-1234',
        ]);
        $this->assertContains($resp['status'], [200, 201, 202, 409, 422], 'A26 API KYC upload');
    }
}
