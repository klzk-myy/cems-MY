<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadEddDocumentRequest;
use App\Models\Compliance\EddDocumentRequest;
use App\Models\Customer;
use App\Models\EnhancedDiligenceRecord;
use App\Services\AuditService;
use App\Services\System\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Customer-facing EDD portal.
 *
 * All access is via signed URLs (no auth required). The customer ID is
 * embedded in the signed URL and validated on every request, so customers
 * can view their own EDD records and upload documents without an account.
 */
class EddCustomerController extends Controller
{
    public function __construct(
        protected DocumentStorageService $documentStorage,
        protected AuditService $auditService,
    ) {}

    /**
     * Resolve the customer from the signed URL parameter.
     */
    private function resolveCustomer(Request $request): Customer
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        /** @var Customer|null $customer */
        $customer = Customer::find((string) $request->query('customer_id'));

        if (! $customer) {
            abort(404, 'Customer not found.');
        }

        return $customer;
    }

    /**
     * Show the customer-facing EDD portal: lists their EDD records and document requests.
     */
    public function index(Request $request): View
    {
        $customer = $this->resolveCustomer($request);

        $eddRecords = EnhancedDiligenceRecord::with(['documentRequests', 'flaggedTransaction'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('compliance.edd.customer.index', compact('eddRecords'));
    }

    /**
     * Show a single EDD record for the customer, with document request status.
     */
    public function show(Request $request, EnhancedDiligenceRecord $eddRecord): View
    {
        $customer = $this->resolveCustomer($request);

        if ($eddRecord->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this EDD record.');
        }

        $eddRecord->load(['documentRequests' => fn ($q) => $q->orderByDesc('created_at')]);

        $customer = Customer::query()->find($eddRecord->customer_id);

        return view('compliance.edd.customer.show', compact('eddRecord', 'customer'));
    }

    /**
     * Signed upload for a document request. Stores file with UUID name.
     */
    public function upload(UploadEddDocumentRequest $request, EddDocumentRequest $eddDocumentRequest): RedirectResponse
    {
        $customer = $this->resolveCustomer($request);

        // eddRecord is no longer auto-eager-loaded (the model's $with was
        // removed); load it explicitly rather than triggering a lazy load.
        $eddDocumentRequest->loadMissing('eddRecord');

        if ($eddDocumentRequest->eddRecord?->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this document request.');
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $storedPath = 'edd-documents/'.Str::uuid().'.'.$extension;

        $file->storeAs('edd-documents', basename($storedPath));

        $eddDocumentRequest->markReceived($storedPath);

        $this->auditService->logWithSeverity(
            'edd_document_uploaded',
            [
                'description' => 'Customer uploaded EDD document: '.($eddDocumentRequest->document_type ?? 'unknown'),
                'edd_document_request_id' => $eddDocumentRequest->id,
                'customer_id' => $customer->id,
            ],
            'info'
        );

        return back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Download an EDD document via signed URL.
     */
    public function download(Request $request, EddDocumentRequest $eddDocumentRequest)
    {
        $customer = $this->resolveCustomer($request);

        $eddDocumentRequest->loadMissing('eddRecord');

        if ($eddDocumentRequest->eddRecord?->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this document.');
        }

        if (! $eddDocumentRequest->file_path) {
            abort(404);
        }

        return $this->documentStorage->download($eddDocumentRequest->file_path);
    }

    /**
     * Generate a signed URL for the customer portal.
     */
    public static function signedUrl(Customer $customer, string $route = 'compliance.edd.customer.index', ?int $expiration = 10080): string
    {
        return URL::signedRoute($route, ['customer_id' => $customer->id], $expiration);
    }
}
