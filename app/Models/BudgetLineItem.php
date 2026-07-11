<?php

namespace App\Models;

use App\States\NeedsRevision;
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

    /**
     * Record an audit trail entry whenever a line item is edited while its
     * submission is in NEEDS_REVISION, capturing old and new values so
     * reviewer/researcher budget disputes can be traced (BR-BUD-08).
     */
    protected static function booted(): void
    {
        static::updating(function (BudgetLineItem $item): void {
            $submission = FormSubmission::find($item->form_submission_id);

            if (!$submission?->status instanceof NeedsRevision) {
                return;
            }

            activity('budget')
                ->performedOn($item)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => [
                        'item_name' => $item->getOriginal('item_name'),
                        'volume' => $item->getOriginal('volume'),
                        'unit_price' => $item->getOriginal('unit_price'),
                        'total' => $item->getOriginal('total'),
                    ],
                    'new' => [
                        'item_name' => $item->item_name,
                        'volume' => $item->volume,
                        'unit_price' => $item->unit_price,
                        'total' => $item->total,
                    ],
                ])
                ->log('budget_line_item_changed');
        });
    }

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
