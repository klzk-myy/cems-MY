<x-app-layout title="PEP Approval Queue">
    <div class="space-y-6">
        <x-page-header title="PEP Approval Queue" description="Pending PEP relationship approval requests" />

        <x-card>
            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th scope="col">ID</th>
                        <th scope="col">Customer</th>
                        <th scope="col">PEP Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Requested</th>
                        <th scope="col">Actions</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($pending as $req)
                            <tr>
                                <td>{{ $req->id }}</td>
                                <td>{{ $req->customer?->name }}</td>
                                <td>{{ $req->pep_type }}</td>
                                <td><x-badge variant="warning">{{ $req->status }}</x-badge></td>
                                <td>{{ $req->created_at->format('d M Y H:i') }}</td>
                                <td class="flex gap-2">
                                    <form action="{{ route('compliance.pep-approvals.approve', $req->id) }}" method="POST">
                                        @csrf
                                        <x-button type="submit" variant="success">Approve</x-button>
                                    </form>
                                    <form action="{{ route('compliance.pep-approvals.reject', $req->id) }}" method="POST"
                                          data-confirm="Reject this PEP approval request?">
                                        @csrf
                                        <input type="hidden" name="reason" value="Rejected by management">
                                        <x-button type="submit" variant="danger">Reject</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-ink-muted py-4">No pending PEP approvals.</td></tr>
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>
            <div class="mt-4">{{ $pending->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
