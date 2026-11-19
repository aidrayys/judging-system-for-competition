<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VotingPeriod extends Model {
    protected $fillable = ['opens_at','closes_at','is_open','is_finalized','finalized_at'];
    protected function casts(): array {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_open' => 'boolean',
            'is_finalized' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }
    public function isOpen(Carbon $now = null): bool {
        $now = ($now ?: now(config('app.display_timezone', 'UTC')))->copy()->setTimezone('UTC');
        if (! $this->is_open) return false;
        if (! $this->opens_at || ! $this->closes_at) return false;
        return $now->between($this->opens_at, $this->closes_at);
    }
    public function statusLabel(Carbon $now = null): string {
        $now = $now ?: now(config('app.display_timezone', 'UTC'));
        if ($this->is_finalized) return 'Final';
        if (! $this->is_open) return 'Closed';
        if (! $this->opens_at || ! $this->closes_at) return 'Not Open';
        if ($now->lt($this->opens_at->copy()->setTimezone(config('app.display_timezone', 'UTC')))) return 'Not Open';
        if ($now->between($this->opens_at->copy()->setTimezone(config('app.display_timezone', 'UTC')), $this->closes_at->copy()->setTimezone(config('app.display_timezone', 'UTC')))) return 'Open';
        return 'Closed';
    }
}
