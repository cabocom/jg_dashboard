<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Models\Concerns;

trait HasApprovalState
{
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['APPROVED', 'REJECTED', 'TIMEOUT', 'CANCELLED']);
    }
}
