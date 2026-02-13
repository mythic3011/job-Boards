<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Get all valid status values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get valid transitions from current status.
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::PENDING => [self::APPROVED, self::REJECTED],
            self::APPROVED => [], // Cannot change once approved
            self::REJECTED => [], // Cannot change once rejected
        };
    }

    /**
     * Check if transition to new status is allowed.
     */
    public function canTransitionTo(ApplicationStatus $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }
}
