<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    /**
     * Notification type keys mirror NotificationDispatcher::shouldDeliver:
     * trailing class-name words converted to snake_case.
     */
    private const TYPES = [
        'large_transaction' => 'Large transaction approvals',
        'transaction_approved' => 'Transaction approved',
        'emergency_counter_closure' => 'Emergency counter closures',
        'revaluation' => 'Revaluation results',
        'report_email' => 'Report delivery emails',
        'system_alert' => 'System alerts',
    ];

    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('notifications.preferences', [
            'types' => self::TYPES,
            'prefs' => $user->notification_preferences ?? [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'types' => ['nullable', 'array'],
            'types.*' => ['string'],
            'digest_enabled' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Only keys present in the form are stored; absent = disabled.
        $selected = array_fill_keys(array_keys(self::TYPES), false);

        foreach (array_keys($validated['types'] ?? []) as $key) {
            if (array_key_exists($key, self::TYPES)) {
                $selected[$key] = true;
            }
        }

        // Checkbox absent from the payload means the digest opt-out box was
        // left unticked; the form always renders it so absence is explicit.
        $selected['digest_enabled'] = $request->boolean('digest_enabled');

        $user->update(['notification_preferences' => $selected]);

        return redirect()
            ->route('notifications.preferences')
            ->with('success', 'Notification preferences updated.');
    }
}
