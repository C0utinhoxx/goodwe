<?php

namespace App\Contracts;

use App\Models\Establishment;

interface DemandDetector
{
    /** Validate real historical/current evidence against a configured, versioned policy. */
    public function accepts(Establishment $establishment, string $criteriaReference, string $eventType, array $evidence): bool;
}
