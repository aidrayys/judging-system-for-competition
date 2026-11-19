<?php

namespace App\Services;

use App\Models\CompetitionSetting;

/**
 * Single source of truth for the judging period.
 *
 * The state is persisted in the `competition_settings` table
 * (key = `judging_open`, value = '1' | '0'). It is read from the database on
 * every request - never from a hard coded or client side value.
 */
class JudgingPeriod
{
    public const KEY = 'judging_open';

    /**
     * @return bool true when judges are allowed to score and submit.
     */
    public function isOpen(): bool
    {
        $value = CompetitionSetting::get(self::KEY);

        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    public function open(): void
    {
        CompetitionSetting::put(self::KEY, '1');
        CompetitionSetting::put('judging_opened_at', now()->toDateTimeString());
        CompetitionSetting::put('judging_closed_at', '');
    }

    public function close(): void
    {
        CompetitionSetting::put(self::KEY, '0');
        CompetitionSetting::put('judging_closed_at', now()->toDateTimeString());
    }

    public function openedAt(): ?string
    {
        $value = CompetitionSetting::get('judging_opened_at');

        return filled($value) ? (string) $value : null;
    }

    public function closedAt(): ?string
    {
        $value = CompetitionSetting::get('judging_closed_at');

        return filled($value) ? (string) $value : null;
    }

    public function statusLabel(): string
    {
        return $this->isOpen() ? 'Open' : 'Closed';
    }
}
