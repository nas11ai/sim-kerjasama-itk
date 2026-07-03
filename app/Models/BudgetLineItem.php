<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property int $budget_component_id
 * @property string $item_name
 * @property int $volume
 * @property string $unit
 * @property int $unit_price
 * @property int $total
 * @property-read FormSubmission $formSubmission
 * @property-read BudgetComponent $budgetComponent
 */
class BudgetLineItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'form_submission_id',
        'budget_component_id',
        'item_name',
        'volume',
        'unit',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'volume' => 'integer',
        'unit_price' => 'integer',
        'total' => 'integer',
    ];

    /** @return BelongsTo<FormSubmission, $this> */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /** @return BelongsTo<BudgetComponent, $this> */
    public function budgetComponent(): BelongsTo
    {
        return $this->belongsTo(BudgetComponent::class);
    }
}
