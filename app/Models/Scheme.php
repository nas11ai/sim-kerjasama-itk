<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Scheme extends Model
{
    protected $fillable =[
        'rules'
    ];
protected $casts = ['rules' => 'array'];

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
