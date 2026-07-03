<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property-read Collection<int, BudgetLineItem> $budgetLineItems
 */
class BudgetComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return HasMany<BudgetLineItem, $this> */
    public function budgetLineItems(): HasMany
    {
        return $this->hasMany(BudgetLineItem::class);
    }
}
