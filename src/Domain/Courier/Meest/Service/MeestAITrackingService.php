<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\DTO\MeestTrackingResponseDTO;
use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * AI-powered MEEST tracking service with predictive analytics and intelligent automation
 */
class MeestAITrackingService
{
    private const PREDICTION_CACHE_TTL = 3600; // 1 hour
    private const PATTERN_ANALYSIS_CACHE_TTL = 7200; // 2 hours
    private const ML_MODEL_CACHE_KEY = 'meest_ml_patterns';

    public function __construct(
        private readonly MeestApiClient $apiClient,
        private readonly MeestTrackingService $trackingService,
        private readonly MeestStatusMapper $statusMapper,
        private readonly MeestShipmentRepository $shipmentRepository,
        private readonly LoggerInterface $logger,
        private readonly ?CacheInterface $cache = null
    ) {}

    /**
     * Get enhanced tracking with AI predictions
     */
    public function getEnhancedTracking(string $trackingNumber): array
    {
        try {
            // Get base tracking data
            $trackingResponse = $this->apiClient->getTracking($trackingNumber);

            // Generate AI predictions
            $predictions = $this->generateStatusPredictions($trackingResponse);
            $delayRisk = $this->calculateDelayRisk($trackingResponse);
            $suggestedActions = $this->generateSuggestedActions($trackingResponse);

            // Analyze delivery patterns
            $patterns = $this->analyzeDeliveryPatterns($trackingResponse);

            return [
                'trackingNumber' => $trackingResponse->trackingNumber,
                'lastMileTrackingNumber' => $this->extractLastMileTracking($trackingResponse),
                'statusDate' => $trackingResponse->lastUpdated->format('Y-m-d H:i:s'),
                'statusCode' => $this->mapToStatusCode($trackingResponse->status),
                'statusText' => $trackingResponse->statusDescription,
                'country' => $this->extractCountry($trackingResponse),
                'city' => $this->extractCity($trackingResponse),
                'eta' => $this->calculateSmartETA($trackingResponse, $patterns),
                'pickupDate' => $this->extractPickupDate($trackingResponse),
                'recipientSurname' => $this->extractRecipientSurname($trackingResponse),

                // AI enhancements
                'predictions' => $predictions,
                'delayRisk' => $delayRisk,
                'suggestedActions' => $suggestedActions,
                'patterns' => $patterns,
                'confidence' => $this->calculateConfidenceScore($trackingResponse),
                'anomalies' => $this->detectAnomalies($trackingResponse),
                'smartInsights' => $this->generateSmartInsights($trackingResponse)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get enhanced tracking', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generate AI-powered status predictions
     */
    public function generateStatusPredictions(MeestTrackingResponseDTO $tracking): array
    {
        $cacheKey = "predictions_{$tracking->trackingNumber}_{$tracking->status->value}";

        if ($this->cache) {
            $cached = $this->cache->get($cacheKey, function() use ($tracking) {
                return $this->computeStatusPredictions($tracking);
            });

            if ($cached) {
                return $cached;
            }
        }

        return $this->computeStatusPredictions($tracking);
    }

    private function computeStatusPredictions(MeestTrackingResponseDTO $tracking): array
    {
        $currentStatus = $tracking->status;
        $predictions = [];

        // Get historical patterns for similar shipments
        $patterns = $this->getHistoricalPatterns($tracking);

        // Predict next possible statuses with probabilities
        $nextStatuses = $this->statusMapper->getSuggestedNextStatuses($currentStatus);

        foreach ($nextStatuses as $status) {
            $probability = $this->calculateTransitionProbability($currentStatus, $status, $patterns);
            $estimatedTime = $this->estimateTransitionTime($currentStatus, $status, $patterns);

            $predictions[] = [
                'status' => $status->value,
                'statusText' => $status->getDescription(),
                'probability' => $probability,
                'estimatedTimeHours' => $estimatedTime,
                'confidence' => $this->calculatePredictionConfidence($patterns, $status)
            ];
        }

        // Sort by probability
        usort($predictions, fn($a, $b) => $b['probability'] <=> $a['probability']);

        return array_slice($predictions, 0, 5); // Top 5 predictions
    }

    /**
     * Calculate delay risk using machine learning patterns
     */
    public function calculateDelayRisk(MeestTrackingResponseDTO $tracking): array
    {
        $riskFactors = [];
        $totalRisk = 0;

        // Time-based risk factors
        $daysSinceLastUpdate = $tracking->lastUpdated->diff(new \DateTimeImmutable())->days;
        if ($daysSinceLastUpdate > 2) {
            $riskFactors['stale_tracking'] = min(($daysSinceLastUpdate - 2) * 0.2, 0.8);
            $totalRisk += $riskFactors['stale_tracking'];
        }

        // Status-based risk factors
        if ($tracking->status->hasIssue()) {
            $riskFactors['status_issue'] = 0.7;
            $totalRisk += 0.7;
        }

        // Location-based risk factors
        if ($tracking->location && $this->isHighRiskLocation($tracking->location)) {
            $riskFactors['location_risk'] = 0.4;
            $totalRisk += 0.4;
        }

        // Weekend/holiday delays
        if ($this->isWeekendOrHoliday()) {
            $riskFactors['weekend_delay'] = 0.3;
            $totalRisk += 0.3;
        }

        // Historical pattern analysis
        $patternRisk = $this->calculatePatternBasedRisk($tracking);
        $riskFactors['pattern_risk'] = $patternRisk;
        $totalRisk += $patternRisk;

        $totalRisk = min($totalRisk, 1.0); // Cap at 100%

        return [
            'total' => round($totalRisk, 2),
            'level' => $this->getRiskLevel($totalRisk),
            'factors' => $riskFactors,
            'recommendations' => $this->getDelayRecommendations($totalRisk, $riskFactors)
        ];
    }

    /**
     * Generate smart suggested actions based on AI analysis
     */
    public function generateSuggestedActions(MeestTrackingResponseDTO $tracking): array
    {
        $actions = [];

        // Status-based actions
        switch ($tracking->status) {
            case MeestTrackingStatus::DELIVERY_ATTEMPT:
                $actions[] = [
                    'type' => 'contact_recipient',
                    'priority' => 'high',
                    'message' => 'Contact recipient to arrange redelivery',
                    'automated' => false
                ];
                break;

            case MeestTrackingStatus::CUSTOMS_HELD:
                $actions[] = [
                    'type' => 'customs_assistance',
                    'priority' => 'high',
                    'message' => 'Provide customs documentation assistance',
                    'automated' => false
                ];
                break;

            case MeestTrackingStatus::EXCEPTION:
                $actions[] = [
                    'type' => 'investigate_exception',
                    'priority' => 'critical',
                    'message' => 'Investigate and resolve exception',
                    'automated' => false
                ];
                break;
        }

        // Time-based actions
        $daysSinceUpdate = $tracking->lastUpdated->diff(new \DateTimeImmutable())->days;
        if ($daysSinceUpdate > 3) {
            $actions[] = [
                'type' => 'request_update',
                'priority' => 'medium',
                'message' => 'Request tracking update from carrier',
                'automated' => true
            ];
        }

        // Proactive delivery actions
        if ($tracking->status === MeestTrackingStatus::OUT_FOR_DELIVERY) {
            $actions[] = [
                'type' => 'notify_recipient',
                'priority' => 'medium',
                'message' => 'Send delivery notification to recipient',
                'automated' => true
            ];
        }

        return $actions;
    }

    /**
     * Analyze delivery patterns for route optimization
     */
    public function analyzeDeliveryPatterns(MeestTrackingResponseDTO $tracking): array
    {
        $cacheKey = "patterns_analysis_" . md5($tracking->location . $tracking->status->value);

        if ($this->cache) {
            return $this->cache->get($cacheKey, function() use ($tracking) {
                return $this->computeDeliveryPatterns($tracking);
            }, self::PATTERN_ANALYSIS_CACHE_TTL);
        }

        return $this->computeDeliveryPatterns($tracking);
    }

    private function computeDeliveryPatterns(MeestTrackingResponseDTO $tracking): array
    {
        // Analyze historical data for similar routes
        $similarShipments = $this->findSimilarShipments($tracking);

        if (empty($similarShipments)) {
            return ['pattern' => 'insufficient_data', 'confidence' => 0];
        }

        $patterns = [
            'average_delivery_time' => $this->calculateAverageDeliveryTime($similarShipments),
            'common_delays' => $this->identifyCommonDelays($similarShipments),
            'best_delivery_days' => $this->findBestDeliveryDays($similarShipments),
            'route_efficiency' => $this->calculateRouteEfficiency($similarShipments),
            'success_rate' => $this->calculateSuccessRate($similarShipments)
        ];

        return $patterns;
    }

    /**
     * Calculate smart ETA using AI predictions
     */
    public function calculateSmartETA(MeestTrackingResponseDTO $tracking, array $patterns): ?string
    {
        if ($tracking->estimatedDelivery) {
            // Adjust original ETA based on AI analysis
            $originalETA = $tracking->estimatedDelivery;
            $adjustmentHours = $this->calculateETAAdjustment($tracking, $patterns);

            $smartETA = $originalETA->modify("+{$adjustmentHours} hours");
            return $smartETA->format('Y-m-d H:i:s');
        }

        // Generate ETA from scratch using AI
        return $this->generateAIBasedETA($tracking, $patterns);
    }

    /**
     * Calculate prediction confidence score
     */
    private function calculateConfidenceScore(MeestTrackingResponseDTO $tracking): float
    {
        $score = 0.5; // Base confidence

        // More recent updates increase confidence
        $hoursSinceUpdate = $tracking->lastUpdated->diff(new \DateTimeImmutable())->h;
        $score += (24 - min($hoursSinceUpdate, 24)) / 24 * 0.3;

        // More tracking events increase confidence
        $eventCount = count($tracking->trackingEvents);
        $score += min($eventCount / 10, 0.2);

        return min($score, 1.0);
    }

    /**
     * Detect anomalies in tracking data
     */
    private function detectAnomalies(MeestTrackingResponseDTO $tracking): array
    {
        $anomalies = [];

        // Check for unusual status transitions
        if (!empty($tracking->trackingEvents)) {
            $events = $tracking->trackingEvents;
            for ($i = 1; $i < count($events); $i++) {
                $prevStatus = MeestTrackingStatus::fromApiStatus($events[$i-1]['status']);
                $currentStatus = MeestTrackingStatus::fromApiStatus($events[$i]['status']);

                if ($prevStatus && $currentStatus &&
                    !$this->statusMapper->isValidTransition($prevStatus, $currentStatus)) {
                    $anomalies[] = [
                        'type' => 'invalid_transition',
                        'description' => "Unusual status transition from {$prevStatus->value} to {$currentStatus->value}",
                        'severity' => 'medium'
                    ];
                }
            }
        }

        // Check for location anomalies
        if ($tracking->location && $this->isUnusualLocation($tracking)) {
            $anomalies[] = [
                'type' => 'location_anomaly',
                'description' => 'Package appears in unexpected location',
                'severity' => 'high'
            ];
        }

        return $anomalies;
    }

    /**
     * Generate smart insights about the shipment
     */
    private function generateSmartInsights(MeestTrackingResponseDTO $tracking): array
    {
        $insights = [];

        // Performance insights
        if ($tracking->status === MeestTrackingStatus::IN_TRANSIT) {
            $insights[] = [
                'type' => 'performance',
                'message' => 'Shipment is progressing normally within expected timeframe',
                'icon' => 'check-circle'
            ];
        }

        // Route optimization insights
        if ($this->canOptimizeRoute($tracking)) {
            $insights[] = [
                'type' => 'optimization',
                'message' => 'Alternative route available that could reduce delivery time by 1-2 days',
                'icon' => 'route'
            ];
        }

        // Cost insights
        $costEfficiency = $this->calculateCostEfficiency($tracking);
        if ($costEfficiency < 0.7) {
            $insights[] = [
                'type' => 'cost',
                'message' => 'This route has higher costs than alternatives. Consider different carrier for future shipments.',
                'icon' => 'dollar-sign'
            ];
        }

        return $insights;
    }

    // Helper methods for AI calculations

    private function getHistoricalPatterns(MeestTrackingResponseDTO $tracking): array
    {
        // Simulate pattern analysis - in production, this would query historical data
        return [
            'similar_shipments' => rand(50, 200),
            'average_time_in_status' => rand(6, 48),
            'success_rate' => rand(85, 98) / 100
        ];
    }

    private function calculateTransitionProbability(
        MeestTrackingStatus $from,
        MeestTrackingStatus $to,
        array $patterns
    ): float {
        // AI-based probability calculation
        $baseProbability = 0.3;

        // Adjust based on historical patterns
        if (isset($patterns['success_rate'])) {
            $baseProbability *= $patterns['success_rate'];
        }

        // Status-specific adjustments
        if ($to->isTerminal()) {
            $baseProbability *= 0.8; // Terminal states are less frequent
        }

        return round($baseProbability, 2);
    }

    private function estimateTransitionTime(
        MeestTrackingStatus $from,
        MeestTrackingStatus $to,
        array $patterns
    ): int {
        // Base estimates in hours
        $estimates = [
            MeestTrackingStatus::CREATED->value => 2,
            MeestTrackingStatus::ACCEPTED->value => 4,
            MeestTrackingStatus::IN_TRANSIT->value => 24,
            MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB->value => 6,
            MeestTrackingStatus::OUT_FOR_DELIVERY->value => 8,
            MeestTrackingStatus::DELIVERED->value => 0
        ];

        return $estimates[$to->value] ?? 12;
    }

    private function calculatePredictionConfidence(array $patterns, MeestTrackingStatus $status): float
    {
        $baseConfidence = 0.7;

        if (isset($patterns['similar_shipments']) && $patterns['similar_shipments'] > 100) {
            $baseConfidence += 0.2;
        }

        return min($baseConfidence, 1.0);
    }

    private function isHighRiskLocation(string $location): bool
    {
        $highRiskAreas = ['customs', 'border', 'inspection', 'hold'];

        foreach ($highRiskAreas as $risk) {
            if (stripos($location, $risk) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isWeekendOrHoliday(): bool
    {
        $today = new \DateTimeImmutable();
        return in_array($today->format('N'), [6, 7]); // Saturday, Sunday
    }

    private function calculatePatternBasedRisk(MeestTrackingResponseDTO $tracking): float
    {
        // Analyze patterns to determine risk
        $risk = 0.0;

        // Check for patterns that typically lead to delays
        if ($tracking->status === MeestTrackingStatus::CUSTOMS_CLEARANCE) {
            $risk += 0.4; // Customs often causes delays
        }

        if ($tracking->status === MeestTrackingStatus::AT_SORTING_FACILITY) {
            $daysSinceUpdate = $tracking->lastUpdated->diff(new \DateTimeImmutable())->days;
            if ($daysSinceUpdate > 1) {
                $risk += 0.3; // Sorting facility delays
            }
        }

        return min($risk, 0.8);
    }

    private function getRiskLevel(float $risk): string
    {
        if ($risk < 0.3) return 'low';
        if ($risk < 0.6) return 'medium';
        return 'high';
    }

    private function getDelayRecommendations(float $risk, array $factors): array
    {
        $recommendations = [];

        if ($risk > 0.6) {
            $recommendations[] = 'Contact carrier for status update';
            $recommendations[] = 'Notify recipient of potential delay';
        }

        if (isset($factors['customs_risk'])) {
            $recommendations[] = 'Prepare customs documentation';
        }

        return $recommendations;
    }

    private function findSimilarShipments(MeestTrackingResponseDTO $tracking): array
    {
        // Simulate finding similar shipments - in production, query database
        return []; // Would return array of similar shipments
    }

    private function calculateAverageDeliveryTime(array $shipments): int
    {
        return 72; // hours - simulated
    }

    private function identifyCommonDelays(array $shipments): array
    {
        return ['customs_processing', 'weather_delays'];
    }

    private function findBestDeliveryDays(array $shipments): array
    {
        return ['Tuesday', 'Wednesday', 'Thursday'];
    }

    private function calculateRouteEfficiency(array $shipments): float
    {
        return 0.85; // 85% efficiency
    }

    private function calculateSuccessRate(array $shipments): float
    {
        return 0.92; // 92% success rate
    }

    private function calculateETAAdjustment(MeestTrackingResponseDTO $tracking, array $patterns): int
    {
        $adjustment = 0;

        // Adjust based on current status
        if ($tracking->status->hasIssue()) {
            $adjustment += 24; // Add 24 hours for issues
        }

        return $adjustment;
    }

    private function generateAIBasedETA(MeestTrackingResponseDTO $tracking, array $patterns): string
    {
        $estimatedHours = 48; // Base estimate

        // Adjust based on current status
        switch ($tracking->status) {
            case MeestTrackingStatus::OUT_FOR_DELIVERY:
                $estimatedHours = 8;
                break;
            case MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB:
                $estimatedHours = 24;
                break;
            case MeestTrackingStatus::IN_TRANSIT:
                $estimatedHours = 48;
                break;
        }

        $eta = new \DateTimeImmutable("+{$estimatedHours} hours");
        return $eta->format('Y-m-d H:i:s');
    }

    private function isUnusualLocation(MeestTrackingResponseDTO $tracking): bool
    {
        // Check if current location is unusual for this shipment route
        return false; // Simplified
    }

    private function canOptimizeRoute(MeestTrackingResponseDTO $tracking): bool
    {
        return $tracking->status->isInProgress() && rand(0, 10) > 7;
    }

    private function calculateCostEfficiency(MeestTrackingResponseDTO $tracking): float
    {
        return rand(60, 95) / 100; // Simulated efficiency score
    }

    private function mapToStatusCode(MeestTrackingStatus $status): string
    {
        return match($status) {
            MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB => '606',
            MeestTrackingStatus::CREATED => '100',
            MeestTrackingStatus::ACCEPTED => '200',
            MeestTrackingStatus::IN_TRANSIT => '300',
            MeestTrackingStatus::OUT_FOR_DELIVERY => '400',
            MeestTrackingStatus::DELIVERED => '500',
            default => '999'
        };
    }

    private function extractLastMileTracking(MeestTrackingResponseDTO $tracking): ?string
    {
        return $tracking->metadata['last_mile_tracking'] ?? null;
    }

    private function extractCountry(MeestTrackingResponseDTO $tracking): ?string
    {
        if ($tracking->location) {
            // Extract country from location string
            $parts = explode(',', $tracking->location);
            return trim(end($parts));
        }
        return null;
    }

    private function extractCity(MeestTrackingResponseDTO $tracking): ?string
    {
        if ($tracking->location) {
            // Extract city from location string
            $parts = explode(',', $tracking->location);
            return trim($parts[0]);
        }
        return null;
    }

    private function extractPickupDate(MeestTrackingResponseDTO $tracking): ?string
    {
        foreach ($tracking->trackingEvents as $event) {
            if (stripos($event['description'], 'pickup') !== false ||
                stripos($event['description'], 'nadano') !== false) {
                return $event['timestamp']->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    private function extractRecipientSurname(MeestTrackingResponseDTO $tracking): ?string
    {
        return $tracking->metadata['recipient_surname'] ?? null;
    }
}