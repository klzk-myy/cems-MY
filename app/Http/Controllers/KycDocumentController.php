<?php

namespace App\Http\Controllers;

use App\Enums\CustomerDocumentStatus;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Requests\RejectKycDocumentRequest;
use App\Models\CustomerDocument;
use App\Services\AuditService;
use App\Services\System\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KycDocumentController extends Controller
{
    use ApiResponse;

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

        // Lock the row and re-check pending so verify/reject can't both
        // disposition the same document or double-write audit entries.
        $dispositioned = DB::transaction(function () use ($request, $customerDocument): bool {
            $locked = CustomerDocument::whereKey($customerDocument->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CustomerDocumentStatus::Pending) {
                return false;
            }

            $locked->update([
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

            return true;
        });

        if (! $dispositioned) {
            return $this->errorResponse('Document has already been dispositioned', [], 409);
        }

        return $this->successResponse(null, 'Document verified');
    }

    /**
     * Reject a customer document with a reason.
     */
    public function reject(RejectKycDocumentRequest $request, CustomerDocument $customerDocument): JsonResponse
    {
        $this->authorize('reject', $customerDocument);

        $validated = $request->validated();

        $dispositioned = DB::transaction(function () use ($request, $customerDocument, $validated): bool {
            $locked = CustomerDocument::whereKey($customerDocument->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CustomerDocumentStatus::Pending) {
                return false;
            }

            $locked->update([
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

            return true;
        });

        if (! $dispositioned) {
            return $this->errorResponse('Document has already been dispositioned', [], 409);
        }

        return $this->successResponse(null, 'Document rejected');
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
