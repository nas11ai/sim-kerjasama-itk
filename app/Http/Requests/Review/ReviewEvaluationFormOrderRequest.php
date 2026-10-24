<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/** Urutan baru form evaluasi dari seret-lepas di layar tahap formulir. */
class ReviewEvaluationFormOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:review_evaluation_forms,id',
            'items.*.order' => 'required|integer|min:1',
        ];
    }

    /** @return array<int, int> id form evaluasi => urutannya */
    public function orders(): array
    {
        $orders = [];

        foreach (array_keys($this->array('items')) as $i) {
            $orders[$this->integer("items.{$i}.id")] = $this->integer("items.{$i}.order");
        }

        return $orders;
    }
}
