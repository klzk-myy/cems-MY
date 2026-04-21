<?php

namespace App\Services;

use App\Models\StockReservation;
use App\Models\Transaction;

/**
 * Metrics Service
 *
 * Provides operational metrics for monitoring system health and performance.
 * Tracks key metrics related to stock reservations, approval sync failures,
 * and cancellation requests to help identify potential issues.
 */
class MetricsService
{
    /**
     * Get stock reservation expiry metrics.
     *
     * Calculates the rate of expired stock reservations over a time period.
     * High expiry rates may indicate:
     * - Approval delays
     * - System issues
     * - Process inefficiencies
     *
     * @param  int  $days  Number of days to look back (default: 7)
     * @return array<string, mixed> Metrics containing:
     *                              - total_reservations: int Total reservations created
     *                              - expired_reservations: int Reservations that expired
     *                              - consumed_reservations: int Reservations that were consumed
     *                              - released_reservations: int Reservations that were released
     *                              - expiry_rate: float Percentage of reservations that expired
     *                              - avg_hours_to_expiry: float Average hours before expiry
     */
    public function getStockReservationExpiryMetrics(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $totalReservations = StockReservation::where('created_at', '>=', $startDate)->count();
        $expiredReservations = StockReservation::where('created_at', '>=', $startDate)
            ->where('status', 'expired')
            ->count();
        $consumedReservations = StockReservation::where('created_at', '>=', $startDate)
            ->where('status', 'consumed')
            ->count();
        $releasedReservations = StockReservation::where('created_at', '>=', $startDate)
            ->where('status', 'released')
            ->count();

        $expiryRate = $totalReservations > 0
            ? round(($expiredReservations / $totalReservations) * 100, 2)
            : 0.0;

        // Calculate average hours to expiry for expired reservations
        $expiredWithTimes = StockReservation::where('created_at', '>=', $startDate)
            ->where('status', 'expired')
            ->whereNotNull('expires_at')
            ->get();

        $avgHoursToExpiry = $expiredWithTimes->isNotEmpty()
            ? round($expiredWithTimes->avg(function ($reservation) {
                return $reservation->created_at->diffInHours($reservation->expires_at);
            }), 2)
            : 0.0;

        return [
            'period_days' => $days,
            'total_reservations' => $totalReservations,
            'expired_reservations' => $expiredReservations,
            'consumed_reservations' => $consumedReservations,
            'released_reservations' => $releasedReservations,
            'expiry_rate' => $expiryRate,
            'avg_hours_to_expiry' => $avgHoursToExpiry,
            'threshold_warning' => $expiryRate > 10, // Warn if > 10% expiry rate
            'threshold_critical' => $expiryRate > 25, // Critical if > 25% expiry rate
        ];
    }

    /**
     * Get cancellation request metrics.
     *
     * Tracks transaction cancellation requests and their outcomes.
     * High cancellation rates may indicate:
     * - Process issues
     * - Customer dissatisfaction
     * - Training needs
     *
     * @param  int  $days  Number of days to look back (default: 7)
     * @return array<string, mixed> Metrics containing:
     *                              - total_transactions: int Total transactions
     *                              - cancellation_requests: int Cancellation requests
     *                              - cancellations_approved: int Approved cancellations
     *                              - cancellations_rejected: int Rejected cancellations
     *                              - cancellation_rate: float Percentage of transactions cancelled
     *                              - approval_rate: float Percentage of requests approved
     */
    public function getCancellationRequestMetrics(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $totalTransactions = Transaction::where('created_at', '>=', $startDate)->count();

        // Count transactions that went through cancellation workflow
        $cancellationRequests = Transaction::where('created_at', '>=', $startDate)
            ->where('status', 'Cancelled')
            ->count();

        // Count approved cancellations (transactions in Cancelled status)
        $cancellationsApproved = Transaction::where('created_at', '>=', $startDate)
            ->where('status', 'Cancelled')
            ->count();

        // Count rejected cancellations (transactions that returned to previous status)
        // This is tracked via transition_history containing 'cancellation_rejected'
        $cancellationsRejected = Transaction::where('created_at', '>=', $startDate)
            ->whereJsonContains('transition_history', [['reason' => 'Cancellation rejected']])
            ->count();

        $cancellationRate = $totalTransactions > 0
            ? round(($cancellationRequests / $totalTransactions) * 100, 2)
            : 0.0;

        $totalRequests = $cancellationsApproved + $cancellationsRejected;
        $approvalRate = $totalRequests > 0
            ? round(($cancellationsApproved / $totalRequests) * 100, 2)
            : 0.0;

        // Get recent cancellations by reason
        $recentCancellations = Transaction::where('status', 'Cancelled')
            ->where('updated_at', '>=', now()->subHours(24))
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                // Extract reason from transition history
                $history = $transaction->transition_history ?? [];
                $reason = null;
                foreach (array_reverse($history) as $entry) {
                    if (($entry['to'] ?? '') === 'Cancelled') {
                        $reason = $entry['reason'] ?? null;
                        break;
                    }
                }

                return [
                    'transaction_id' => $transaction->id,
                    'cancelled_at' => $transaction->updated_at->toIso8601String(),
                    'amount_local' => $transaction->amount_local,
                    'currency_code' => $transaction->currency_code,
                    'reason' => $reason,
                ];
            })
            ->toArray();

        return [
            'period_days' => $days,
            'total_transactions' => $totalTransactions,
            'cancellation_requests' => $cancellationRequests,
            'cancellations_approved' => $cancellationsApproved,
            'cancellations_rejected' => $cancellationsRejected,
            'cancellation_rate' => $cancellationRate,
            'approval_rate' => $approvalRate,
            'recent_cancellations' => $recentCancellations,
            'threshold_warning' => $cancellationRate > 2, // Warn if > 2% cancellation rate
            'threshold_critical' => $cancellationRate > 5, // Critical if > 5% cancellation rate
        ];
    }

    /**
     * Get all operational metrics in a single call.
     *
     * @param  int  $days  Number of days to look back (default: 7)
     * @return array<string, mixed> All metrics combined
     */
    public function getAllMetrics(int $days = 7): array
    {
        return [
            'stock_reservation_expiry' => $this->getStockReservationExpiryMetrics($days),
            'cancellation_requests' => $this->getCancellationRequestMetrics($days),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get health summary based on metrics.
     *
     * Returns an overall health status and list of issues requiring attention.
     *
     * @param  int  $days  Number of days to look back (default: 7)
     * @return array<string, mixed> Health summary containing:
     *                              - overall_status: string 'healthy', 'warning', or 'critical'
     *                              - issues: array List of issues requiring attention
     *                              - recommendations: array List of recommendations
     */
    public function getHealthSummary(int $days = 7): array
    {
        $metrics = $this->getAllMetrics($days);
        $issues = [];
        $recommendations = [];

        // Check stock reservation expiry
        if ($metrics['stock_reservation_expiry']['threshold_critical']) {
            $issues[] = [
                'type' => 'critical',
                'metric' => 'stock_reservation_expiry',
                'message' => 'Stock reservation expiry rate is critically high ('.$metrics['stock_reservation_expiry']['expiry_rate'].'%)',
            ];
            $recommendations[] = 'Review approval workflow delays and consider extending reservation timeout';
        } elseif ($metrics['stock_reservation_expiry']['threshold_warning']) {
            $issues[] = [
                'type' => 'warning',
                'metric' => 'stock_reservation_expiry',
                'message' => 'Stock reservation expiry rate is elevated ('.$metrics['stock_reservation_expiry']['expiry_rate'].'%)',
            ];
            $recommendations[] = 'Monitor approval delays and investigate bottlenecks';
        }

        // Check cancellation rate
        if ($metrics['cancellation_requests']['threshold_critical']) {
            $issues[] = [
                'type' => 'critical',
                'metric' => 'cancellation_requests',
                'message' => 'Transaction cancellation rate is critically high ('.$metrics['cancellation_requests']['cancellation_rate'].'%)',
            ];
            $recommendations[] = 'Review cancellation reasons and identify process issues';
        } elseif ($metrics['cancellation_requests']['threshold_warning']) {
            $issues[] = [
                'type' => 'warning',
                'metric' => 'cancellation_requests',
                'message' => 'Transaction cancellation rate is elevated ('.$metrics['cancellation_requests']['cancellation_rate'].'%)',
            ];
            $recommendations[] = 'Monitor cancellation patterns and consider staff training';
        }

        // Determine overall status
        $hasCritical = collect($issues)->contains('type', 'critical');
        $hasWarning = collect($issues)->contains('type', 'warning');

        $overallStatus = $hasCritical ? 'critical' : ($hasWarning ? 'warning' : 'healthy');

        return [
            'overall_status' => $overallStatus,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'metrics' => $metrics,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
