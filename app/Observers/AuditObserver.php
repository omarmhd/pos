<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic audit observer — attach to any Eloquent model to get full
 * created / updated / deleted logging in audit_logs.
 *
 * Fields excluded from logging:
 *   - updated_at, created_at  → noise
 *   - password, remember_token, api_token → sensitive
 *   - PENDING-* voucher_number / entry_number values → transient boot state
 *
 * Usage (in AppServiceProvider::boot):
 *   Sale::observe(AuditObserver::class);
 */
class AuditObserver
{
    /** Fields that are never interesting to log */
    private const EXCLUDE = [
        'updated_at', 'created_at',
        'password', 'remember_token', 'api_token',
    ];

    public function created(Model $model): void
    {
        $new = $this->clean($model->getAttributes());
        if (empty($new)) {
            return;
        }
        $this->write($model, 'created', null, $new);
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();

        // Remove noise-only changes
        foreach (self::EXCLUDE as $key) {
            unset($dirty[$key]);
        }

        if (empty($dirty)) {
            return;
        }

        $old = $this->clean(array_intersect_key($model->getOriginal(), $dirty));
        $new = $this->clean($dirty);

        $this->write($model, 'updated', $old ?: null, $new ?: null);
    }

    public function deleted(Model $model): void
    {
        $old = $this->clean($model->getAttributes());
        $this->write($model, 'deleted', $old ?: null, null);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function clean(array $attrs): array
    {
        foreach (self::EXCLUDE as $key) {
            unset($attrs[$key]);
        }
        // Remove PENDING-* transient values (set in boot() before ID is known)
        foreach ($attrs as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'PENDING-')) {
                unset($attrs[$key]);
            }
        }
        return $attrs;
    }

    private function write(Model $model, string $action, ?array $old, ?array $new): void
    {
        try {
            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->getKey(),
                'action'         => $action,
                'old_values'     => $old,
                'new_values'     => $new,
                'ip_address'     => request()?->ip(),
            ]);
        } catch (\Throwable) {
            // Never let audit failure break the main transaction
        }
    }
}
