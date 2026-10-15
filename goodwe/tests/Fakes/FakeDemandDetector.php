<?php

namespace Tests\Fakes;

use App\Contracts\DemandDetector;
use App\Models\Establishment;

/** Deterministic adapter for isolated tests only; production has no configured detector. */
class FakeDemandDetector implements DemandDetector
{
    public function accepts(Establishment $establishment, string $criteriaReference, string $eventType, array $evidence): bool
    {
        return $criteriaReference === 'test-policy-v1' && ($evidence['validated'] ?? false) === true && ! empty($evidence['history']) && ! empty($evidence['occupancy']);
    }
}
