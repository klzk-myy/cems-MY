<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\SystemLog;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CustomerKycController
 *
 * Handles KYC document management for customers.
 * Provides document upload, verification, and deletion operations.
 */
class CustomerKycController extends Controller
{
    public function __construct(
        protected EncryptionService $encryptionService
    ) {}

    /**
     * Show the KYC document management form.
     *
     * @return \Illuminate\View\View
     */
    public function kyc(Customer $customer)
    {
        // Only compliance officers and admins can verify documents
        $canVerify = auth()->user()->isComplianceOfficer() || auth()->user()->isAdmin();

        $documentTypes = CustomerDocument::DOCUMENT_TYPES;

        $documents = $customer->documents()->with(['uploader', 'verifier'])->get();

        return view('customers.kyc', compact(
            'customer',
            'documents',
            'documentTypes',
            'canVerify'
        ));
    }

    /**
     * Handle KYC document upload.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadDocument(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:MyKad_Front,MyKad_Back,Passport,Proof_of_Address,Others'],
            'document_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
            'expiry_date' => 'nullable|date|after:today',
        ]);

        $file = $request->file('document_file');

        // Store file with encryption consideration
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('customer-documents/'.$customer->id, $filename, 'local');

        // Calculate file hash for integrity
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Create document record
        $document = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'file_hash' => $fileHash,
            'file_size' => $file->getSize(),
            'encrypted' => true,
            'uploaded_by' => auth()->id(),
            'expiry_date' => $validated['expiry_date'] ?? null,
        ]);

        // Log document upload
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'customer_document_uploaded',
            'entity_type' => 'CustomerDocument',
            'entity_id' => $document->id,
            'new_values' => [
                'customer_id' => $customer->id,
                'document_type' => $document->document_type,
                'file_size' => $document->file_size,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('customers.kyc', $customer)
            ->with('success', 'Document uploaded successfully.');
    }

    /**
     * Verify a KYC document.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyDocument(Request $request, Customer $customer, CustomerDocument $document)
    {
        // Only compliance officers and admins can verify
        if (! auth()->user()->isComplianceOfficer() && ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized. Compliance Officer or Admin access required.');
        }

        if ($document->customer_id !== $customer->id) {
            abort(404, 'Document does not belong to this customer.');
        }

        $document->update([
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        // Log verification
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'customer_document_verified',
            'severity' => 'INFO',
            'entity_type' => 'CustomerDocument',
            'entity_id' => $document->id,
            'new_values' => [
                'customer_id' => $customer->id,
                'document_type' => $document->document_type,
                'verified_by' => auth()->id(),
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('customers.kyc', $customer)
            ->with('success', 'Document verified successfully.');
    }

    /**
     * Delete a KYC document.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteDocument(Request $request, Customer $customer, CustomerDocument $document)
    {
        if ($document->customer_id !== $customer->id) {
            abort(404, 'Document does not belong to this customer.');
        }

        // Only uploader, manager, or admin can delete
        $canDelete = auth()->id() === $document->uploaded_by
            || auth()->user()->isManager()
            || auth()->user()->isAdmin();

        if (! $canDelete) {
            abort(403, 'Unauthorized to delete this document.');
        }

        // Delete the file
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $documentType = $document->document_type;
        $document->delete();

        // Log document deletion
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'customer_document_deleted',
            'entity_type' => 'CustomerDocument',
            'entity_id' => $document->id,
            'old_values' => [
                'customer_id' => $customer->id,
                'document_type' => $documentType,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('customers.kyc', $customer)
            ->with('success', 'Document deleted successfully.');
    }
}
