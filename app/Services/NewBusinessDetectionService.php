<?php

namespace App\Services;

use App\Models\Business;
use Carbon\Carbon;

class NewBusinessDetectionService
{
    /**
     * Calculate new business score based on multiple signals
     */
    public function calculateNewBusinessScore(Business $business, array $detail, array $reviews = [], array $photos = []): array
    {
        $score = 0;
        $signals = [];
        
        // Signal 1: First Review Date (highest weight)
        $firstReviewSignal = $this->analyzeFirstReviewDate($reviews);
        $score += $firstReviewSignal['score'];
        $signals = array_merge($signals, $firstReviewSignal['signals']);
        
        // Signal 2: Review Burst (growth rate)
        $reviewBurstSignal = $this->analyzeReviewBurst($business, $detail);
        $score += $reviewBurstSignal['score'];
        $signals = array_merge($signals, $reviewBurstSignal['signals']);
        
        // Signal 3: Photo Activity
        $photoSignal = $this->analyzePhotoActivity($photos);
        $score += $photoSignal['score'];
        $signals = array_merge($signals, $photoSignal['signals']);
        
        // Signal 4: Low Review Count (early stage indicator)
        $reviewCountSignal = $this->analyzeReviewCount($detail);
        $score += $reviewCountSignal['score'];
        $signals = array_merge($signals, $reviewCountSignal['signals']);
        
        // Signal 5: Business Status from Google
        $statusSignal = $this->analyzeBusinessStatus($detail);
        $score += $statusSignal['score'];
        $signals = array_merge($signals, $statusSignal['signals']);
        
        // Signal 6: Newly Discovered (not in DB before)
        $discoverySignal = $this->analyzeNewlyDiscovered($business);
        $score += $discoverySignal['score'];
        $signals = array_merge($signals, $discoverySignal['signals']);
        
        // Signal 7: Rating Improvement
        $ratingSignal = $this->analyzeRatingImprovement($business, $detail);
        $score += $ratingSignal['score'];
        $signals = array_merge($signals, $ratingSignal['signals']);
        
        // Signal 8: Review Spike
        $spikeSignal = $this->analyzeReviewSpike($business, $detail);
        $score += $spikeSignal['score'];
        $signals = array_merge($signals, $spikeSignal['signals']);
        
        // Calculate confidence level
        $confidence = $this->calculateConfidence($signals);
        
        // Determine business age estimate
        $ageEstimate = $this->determineBusinessAge($signals, $firstReviewSignal);
        
        return [
            'score' => min(100, $score),
            'signals' => $signals,
            'confidence' => $confidence,
            'business_age_estimate' => $ageEstimate,
            'metadata_analysis' => [
                'oldest_review_date' => $this->getOldestReviewDate($reviews),
                'newest_review_date' => $this->getNewestReviewDate($reviews),
                'review_age_months' => $this->getReviewAgeMonths($reviews),
                'photo_count' => count($photos),
                'has_recent_activity' => $this->hasRecentActivity($reviews),
                'business_age_estimate' => $ageEstimate,
                'confidence_level' => $confidence,
            ]
        ];
    }

    /**
     * Signal 1: Analyze first review date
     */
    private function analyzeFirstReviewDate(array $reviews): array
    {
        $signals = [];
        $score = 0;
        
        if (empty($reviews)) {
            $signals['no_reviews'] = true;
            $score += 15; // No reviews could indicate very new business
            return ['score' => $score, 'signals' => $signals];
        }
        
        $firstReviewDate = $this->getOldestReviewDate($reviews);
        if (!$firstReviewDate) {
            return ['score' => 0, 'signals' => []];
        }
        
        $daysSince = Carbon::parse($firstReviewDate)->diffInDays(now());
        
        if ($daysSince < 7) {
            $score += 60;
            $signals['ultra_new_review'] = true;
        } elseif ($daysSince < 30) {
            $score += 50;
            $signals['very_new_review'] = true;
        } elseif ($daysSince < 90) {
            $score += 35;
            $signals['new_review'] = true;
        } elseif ($daysSince < 180) {
            $score += 20;
            $signals['recent_review'] = true;
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Signal 2: Analyze review burst (growth rate) with 30-day time window
     * Enhanced per brief requirement: >40% dalam 30 hari
     */
    private function analyzeReviewBurst(Business $business, array $detail): array
    {
        $signals = [];
        $score = 0;
        
        $currentReviewCount = $detail['user_ratings_total'] ?? 0;
        $previousReviewCount = $business->review_count ?? 0;
        
        if (!$business->exists) {
            // New business - check if it has good review count for a new business
            if ($currentReviewCount >= 5 && $currentReviewCount <= 20) {
                $score += 20;
                $signals['good_initial_reviews'] = true;
            }
            return ['score' => $score, 'signals' => $signals];
        }
        
        // Check time window - must be within 30 days per brief
        $lastUpdate = $business->last_fetched;
        if ($lastUpdate) {
            $daysSinceUpdate = Carbon::parse($lastUpdate)->diffInDays(now());
            
            // Only count as burst if within 30-day window
            if ($daysSinceUpdate <= 30 && $previousReviewCount > 0) {
                $newReviews = $currentReviewCount - $previousReviewCount;
                $percentage = ($newReviews / $previousReviewCount) * 100;
                
                // Brief requirement: >40%
                if ($percentage > 40) {
                    $score += 30;
                    $signals['review_burst'] = true;
                    $signals['review_burst_details'] = [
                        'percentage' => round($percentage, 1),
                        'new_reviews' => $newReviews,
                        'days' => $daysSinceUpdate
                    ];
                } elseif ($percentage > 20) {
                    $score += 15;
                    $signals['moderate_burst'] = true;
                } elseif ($percentage > 10) {
                    $score += 5;
                    $signals['mild_growth'] = true;
                }
            } elseif ($daysSinceUpdate > 30) {
                // Outside time window - no score for old growth
                $signals['outside_30day_window'] = true;
            }
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Signal 3: Analyze photo activity
     */
    private function analyzePhotoActivity(array $photos): array
    {
        $signals = [];
        $score = 0;
        
        $photoCount = count($photos);
        
        if ($photoCount > 10) {
            $score += 15;
            $signals['active_photos'] = true;
        } elseif ($photoCount > 5) {
            $score += 10;
            $signals['moderate_photos'] = true;
        } elseif ($photoCount > 0) {
            $score += 5;
            $signals['has_photos'] = true;
        }
        
        // Check for recent photos (if metadata available)
        $recentPhotoCount = $this->countRecentPhotos($photos, 90);
        if ($recentPhotoCount > 5) {
            $score += 10;
            $signals['recent_photos'] = true;
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Signal 4: Analyze review count (early stage indicator)
     */
    private function analyzeReviewCount(array $detail): array
    {
        $signals = [];
        $score = 0;
        
        $reviewCount = $detail['user_ratings_total'] ?? 0;
        
        if ($reviewCount < 10 && $reviewCount > 0) {
            $score += 20;
            $signals['early_stage'] = true;
        } elseif ($reviewCount < 20) {
            $score += 10;
            $signals['few_reviews'] = true;
        } elseif ($reviewCount < 50) {
            $score += 5;
            $signals['moderate_reviews'] = true;
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Signal 5: Analyze business status from Google
     */
    private function analyzeBusinessStatus(array $detail): array
    {
        $signals = [];
        $score = 0;
        
        $businessStatus = $detail['business_status'] ?? '';
        
        if ($businessStatus === 'OPENED_RECENTLY') {
            $score += 30;
            $signals['google_recently_opened'] = true;
        } elseif ($businessStatus === 'OPERATIONAL') {
            $score += 5; // Neutral score for operational
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Signal 6: Analyze newly discovered business
     */
    private function analyzeNewlyDiscovered(Business $business): array
    {
        $signals = [];
        $score = 0;
        
        if (!$business->exists) {
            $score += 10;
            $signals['newly_discovered'] = true;
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Signal 7: Analyze rating improvement
     */
    private function analyzeRatingImprovement(Business $business, array $detail): array
    {
        $signals = [];
        $score = 0;
        
        if (!$business->exists || !$business->rating) {
            return ['score' => 0, 'signals' => []];
        }
        
        $currentRating = $detail['rating'] ?? 0;
        
        if ($currentRating > $business->rating + 0.5) {
            $score += 10;
            $signals['rating_improvement'] = true;
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Signal 8: Analyze review spike
     */
    private function analyzeReviewSpike(Business $business, array $detail): array
    {
        $signals = [];
        $score = 0;
        
        if (!$business->exists) {
            return ['score' => 0, 'signals' => []];
        }
        
        $currentReviewCount = $detail['user_ratings_total'] ?? 0;
        $previousReviewCount = $business->review_count ?? 0;
        
        if ($previousReviewCount > 0) {
            $growth = (($currentReviewCount - $previousReviewCount) / $previousReviewCount) * 100;
            
            if ($growth > 100) { // More than doubled
                $score += 15;
                $signals['review_spike'] = true;
            }
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * Calculate confidence level based on signals
     */
    private function calculateConfidence(array $signals): string
    {
        $strongSignals = ['ultra_new_review', 'very_new_review', 'google_recently_opened', 'review_burst'];
        $mediumSignals = ['new_review', 'early_stage', 'active_photos', 'review_spike'];
        
        $strongCount = count(array_intersect_key($signals, array_flip($strongSignals)));
        $mediumCount = count(array_intersect_key($signals, array_flip($mediumSignals)));
        
        if ($strongCount >= 2) {
            return 'high';
        } elseif ($strongCount >= 1 || $mediumCount >= 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Determine business age estimate
     */
    private function determineBusinessAge(array $signals, array $firstReviewSignal): string
    {
        if (isset($signals['ultra_new_review'])) {
            return 'ultra_new';
        } elseif (isset($signals['very_new_review'])) {
            return 'very_new';
        } elseif (isset($signals['new_review'])) {
            return 'new';
        } elseif (isset($signals['recent_review'])) {
            return 'recent';
        } elseif (isset($signals['early_stage'])) {
            return 'new';
        } else {
            return 'established';
        }
    }

    /**
     * Get oldest review date
     */
    private function getOldestReviewDate(array $reviews): ?string
    {
        if (empty($reviews)) {
            return null;
        }
        
        $dates = [];
        foreach ($reviews as $review) {
            if (isset($review['time'])) {
                $dates[] = $review['time'];
            }
        }
        
        if (empty($dates)) {
            return null;
        }
        
        return date('Y-m-d', min($dates));
    }

    /**
     * Get newest review date
     */
    private function getNewestReviewDate(array $reviews): ?string
    {
        if (empty($reviews)) {
            return null;
        }
        
        $dates = [];
        foreach ($reviews as $review) {
            if (isset($review['time'])) {
                $dates[] = $review['time'];
            }
        }
        
        if (empty($dates)) {
            return null;
        }
        
        return date('Y-m-d', max($dates));
    }

    /**
     * Get review age in months
     */
    private function getReviewAgeMonths(array $reviews): ?int
    {
        $oldestDate = $this->getOldestReviewDate($reviews);
        if (!$oldestDate) {
            return null;
        }
        
        return Carbon::parse($oldestDate)->diffInMonths(now());
    }

    /**
     * Check if there's recent activity
     */
    private function hasRecentActivity(array $reviews): bool
    {
        $newestDate = $this->getNewestReviewDate($reviews);
        if (!$newestDate) {
            return false;
        }
        
        return Carbon::parse($newestDate)->diffInDays(now()) < 90;
    }

    /**
     * Count recent photos (within specified days)
     * Enhanced with timestamp validation per brief (<90 days)
     */
    private function countRecentPhotos(array $photos, int $days = 90): int
    {
        $recentCount = 0;
        $thresholdTime = time() - ($days * 24 * 60 * 60);
        
        foreach ($photos as $photo) {
            // Check if photo has timestamp metadata
            if (isset($photo['time'])) {
                if ($photo['time'] > $thresholdTime) {
                    $recentCount++;
                }
            } else {
                // If no timestamp, assume recent (conservative approach)
                $recentCount++;
            }
        }
        
        return $recentCount;
    }
    
    /**
     * Analyze photo age with detailed timestamp checking
     * Per brief requirement: <90 hari
     */
    private function analyzePhotoAge(array $photos): array
    {
        if (empty($photos)) {
            return [
                'has_recent' => false,
                'recent_photo_count' => 0,
                'newest_photo_age_days' => null,
                'unique_uploaders' => 0,
                'total_photos' => 0
            ];
        }
        
        $recentPhotos = 0;
        $uploaders = [];
        $newestPhotoTime = 0;
        $thresholdTime = time() - (90 * 24 * 60 * 60); // 90 days
        
        foreach ($photos as $photo) {
            // Count recent photos (<90 days)
            if (isset($photo['time']) && $photo['time'] > $thresholdTime) {
                $recentPhotos++;
            }
            
            // Track newest photo
            if (isset($photo['time']) && $photo['time'] > $newestPhotoTime) {
                $newestPhotoTime = $photo['time'];
            }
            
            // Track unique uploaders
            if (isset($photo['author_name'])) {
                $uploaders[$photo['author_name']] = true;
            }
        }
        
        return [
            'has_recent' => $recentPhotos > 0,
            'recent_photo_count' => $recentPhotos,
            'newest_photo_age_days' => $newestPhotoTime > 0 
                ? floor((time() - $newestPhotoTime) / (24 * 60 * 60)) 
                : null,
            'unique_uploaders' => count($uploaders),
            'total_photos' => count($photos)
        ];
    }

    /**
     * Generate business indicators for storage
     * Enhanced with photo age analysis per brief
     */
    public function generateBusinessIndicators(Business $business, array $detail, array $reviews = [], array $photos = []): array
    {
        $analysis = $this->calculateNewBusinessScore($business, $detail, $reviews, $photos);
        
        // Enhanced photo analysis with timestamp checking
        $photoAnalysis = $this->analyzePhotoAge($photos);
        
        return [
            'recently_opened' => $this->detectRecentlyOpened($analysis),
            'few_reviews' => ($detail['user_ratings_total'] ?? 0) < 15,
            'low_rating_count' => ($detail['user_ratings_total'] ?? 0) < 5,
            'has_photos' => count($photos) > 0,
            'has_recent_photo' => $photoAnalysis['has_recent'], // Enhanced with timestamp checking
            'photo_analysis' => $photoAnalysis, // Store detailed photo analysis
            'rating_improvement' => $analysis['signals']['rating_improvement'] ?? false,
            'review_spike' => $analysis['signals']['review_spike'] ?? false,
            'review_burst_details' => $analysis['signals']['review_burst_details'] ?? null,
            'is_truly_new' => $this->isTrulyNewBusiness($analysis),
            'newly_discovered' => !$business->exists,
            'metadata_analysis' => $analysis['metadata_analysis'],
            'new_business_confidence' => $analysis['score'],
        ];
    }

    /**
     * Detect if business is recently opened
     */
    private function detectRecentlyOpened(array $analysis): bool
    {
        $signals = $analysis['signals'];
        
        return isset($signals['google_recently_opened']) ||
               isset($signals['ultra_new_review']) ||
               isset($signals['very_new_review']) ||
               in_array($analysis['business_age_estimate'], ['ultra_new', 'very_new', 'new']);
    }

    /**
     * Check if business is truly new
     */
    private function isTrulyNewBusiness(array $analysis): bool
    {
        $signals = $analysis['signals'];
        
        return isset($signals['ultra_new_review']) ||
               isset($signals['very_new_review']) ||
               (isset($signals['new_review']) && $analysis['confidence'] === 'high') ||
               (isset($signals['no_reviews']) && $analysis['confidence'] === 'high');
    }
}
