<?php

namespace App\Http\Controllers;

use App\Enums\CustomerDocumentStatus;
use App\Http\Requests\RejectKycDocumentRequest;
use App\Models\CustomerDocument;
use App\Services\AuditService;
use App\Services\System\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycDocumentController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected DocumentStorageService $documentStorageService,
    ) {}

    /**
     * Verify a customer document.
     */
    public function verify(Request $request, CustomerDocument $customerDocument): JsonResponse
    {
        $this->authorize('verify', $customerDocument);

        $customerDocument->update([
            'status' => CustomerDocumentStatus::Verified->value,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->auditService->logWithSeverity(
            'kyc_document_verified',
            [
                'description' => "KYC document #{$customerDocument->id} verified",
                'document_id' => $customerDocument->id,
                'customer_id' => $customerDocument->customer_id,
            ],
            'INFO'
        );

        return response()->json(['success' => true, 'message' => 'Document verified']);
    }

    /**
     * Reject a customer document with a reason.
     */
    public function reject(RejectKycDocumentRequest $request, CustomerDocument $customerDocument): JsonResponse
    {
        $this->authorize('reject', $customerDocument);

        $validated = $request->validated();

        $customerDocument->update([
            'status' => CustomerDocumentStatus::Rejected->value,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        $this->auditService->logWithSeverity(
            'kyc_document_rejected',
            [
                'description' => "KYC document #{$customerDocument->id} rejected",
                'document_id' => $customerDocument->id,
                'customer_id' => $customerDocument->customer_id,
                'reason' => $validated['reason'],
            ],
            'WARNING'
        );

        return response()->json(['success' => true, 'message' => 'Document rejected']);
    }

    /**
     * Download a customer document.
     *
     * Downloads are routed through DocumentStorageService so the stored
     * path is validated against traversal before hitting the storage disk.
     */
    public function download(CustomerDocument $customerDocument)
    {
        $this->authorize('download', $customerDocument);

        if (! $customerDocument->file_path) {
            abort(404, 'Document file not found');
        }

        try {
            return $this->documentStorageService->download($customerDocument->file_path);
        } catch (\InvalidArgumentException $e) {
            abort(404, 'Document file not found');
        }
    }
}
