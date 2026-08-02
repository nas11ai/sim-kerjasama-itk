<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array|null $rules
 * @property bool $is_active
 * @property string $name
 * @property string $code
 */
class Scheme extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheme_type_id',
        'submission_type_id',
        'name',
        'code',
        'max_budget',
        'max_members',
        'duration_months',
        'rules',
        'is_active',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
        'max_budget' => 'integer',
        'max_members' => 'integer',
        'duration_months' => 'integer',
    ];

    /**
     * Jenis skema.
     */
    public function schemeType(): BelongsTo
    {
        return $this->belongsTo(SchemeType::class);
    }

    public function submissionType(): BelongsTo
    {
        return $this->belongsTo(SubmissionType::class);
    }

    public function getRule(string $key, mixed $default = null): mixed
    {
        return data_get($this->rules, $key, $default);
    }

    public function minReviewerCount(): int
    {
        return (int) $this->getRule('min_reviewer_count', 2);
    }

    public function maxReviewerWorkload(): int
    {
        return (int) $this->getRule('max_reviewer_workload', 10);
    }
}
