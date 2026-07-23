<?php

namespace App\Services\Diagnostics;

use App\Enums\DiagnosticStatus;
use App\Models\DiagnosticSession;
use DomainException;

class DiagnosticStateMachine
{
    private const ALLOWED = [
        'draft' => ['uploading', 'queued', 'cancelled'],
        'uploading' => ['draft', 'queued', 'cancelled'],
        'queued' => ['analyzing', 'failed', 'cancelled'],
        'analyzing' => ['completed', 'failed', 'cancelled'],
        'failed' => ['queued', 'cancelled'],
        'completed' => [], 'cancelled' => [],
    ];

    public function transition(DiagnosticSession $session, DiagnosticStatus $to, array $attributes = []): DiagnosticSession
    {
        if (! in_array($to->value, self::ALLOWED[$session->status] ?? [], true)) {
            throw new DomainException(__('api.invalid_transition'));
        }
        $updated = DiagnosticSession::query()->whereKey($session->id)->where('status', $session->status)->where('lock_version', $session->lock_version)->update(array_merge($attributes, ['status' => $to->value, 'lock_version' => $session->lock_version + 1, 'updated_at' => now()]));
        if ($updated !== 1) {
            throw new DomainException('The diagnostic session changed concurrently.');
        }

        return $session->fresh();
    }
}
