<?php

namespace App\Services\Form;

use App\Data\Request\ReviewFormFieldInput;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Menyamakan field dan opsi sebuah form evaluasi dengan isi layar. Dipanggil dari dalam
 * transaksi CreateReviewEvaluationForm dan ReviseReviewEvaluationForm, yang menulis auditnya.
 */
final class ReviewFormFieldWriter
{
    /**
     * @param  list<ReviewFormFieldInput>  $fields
     *
     * @throws ValidationException
     */
    public static function sync(ReviewEvaluationForm $form, array $fields): void
    {
        $existing = $form->reviewFormFields()->withCount('reviewFormFieldResponses')->get()->keyBy('id');
        $dropped = $existing->except(array_filter(array_map(fn (ReviewFormFieldInput $field): ?int => $field->id, $fields)));

        // Jawaban merujuk field dengan ON DELETE CASCADE dan field evaluasi tanpa soft delete: hapus
        // field yang sudah dijawab ikut menghapus jawabannya tanpa jejak, termasuk yang sudah
        // disubmit dan menurut BR-FE-05 tidak boleh berubah lagi.
        self::assertUnanswered($dropped);
        $dropped->each(fn (ReviewFormField $field) => $field->delete());

        foreach ($fields as $index => $input) {
            $field = self::fieldFor($form, $existing, $input, "fields.{$index}");

            $field->fill([
                'field_type_id' => $input->fieldTypeId,
                'label' => $input->label,
                'description' => $input->description,
                'is_required' => $input->isRequired,
                'order' => $index + 1,
                'validation_rules' => $input->validationRules,
            ])->save();

            if ($input->options !== null) {
                self::syncOptions($field, $input->options, "fields.{$index}.options");
            }
        }
    }

    /**
     * @param  Collection<int, ReviewFormField>  $existing
     *
     * @throws ValidationException
     */
    private static function fieldFor(ReviewEvaluationForm $form, Collection $existing, ReviewFormFieldInput $input, string $key): ReviewFormField
    {
        if ($input->id === null) {
            return new ReviewFormField(['review_evaluation_form_id' => $form->id]);
        }

        $field = $existing->get($input->id) ?? self::notOwned("{$key}.id");

        // Cermin BR-FE-23 pada field evaluasi: jawaban lama tersimpan sebagai teks tanpa tipe,
        // jadi berganti tipe membuatnya tak terbaca renderer tipe barunya.
        if ($field->review_form_field_responses_count > 0 && $field->field_type_id !== $input->fieldTypeId) {
            throw ValidationException::withMessages([
                "{$key}.field_type_id" => "Field \"{$field->label}\" sudah dijawab reviewer, jadi tipenya tidak dapat diubah. Buat field baru.",
            ]);
        }

        return $field;
    }

    /**
     * @param  Collection<int, ReviewFormField>  $dropped
     *
     * @throws ValidationException
     */
    private static function assertUnanswered(Collection $dropped): void
    {
        $answered = $dropped->filter(fn (ReviewFormField $field): bool => $field->review_form_field_responses_count > 0);

        if ($answered->isEmpty()) {
            return;
        }

        $rincian = $answered->map(fn (ReviewFormField $field): string => "\"{$field->label}\" ({$field->review_form_field_responses_count} jawaban)");

        throw ValidationException::withMessages([
            'fields' => 'Field '.$rincian->implode(', ').' sudah dijawab reviewer, jadi tidak dapat dihapus.',
        ]);
    }

    /**
     * @param  list<array{id: int|null, label: string, value: string|null}>  $options
     *
     * @throws ValidationException
     */
    private static function syncOptions(ReviewFormField $field, array $options, string $key): void
    {
        $existing = $field->reviewFormFieldOptions()->get()->keyBy('id');

        $existing->except(array_filter(array_column($options, 'id')))
            ->each(fn (ReviewFormFieldOption $option) => $option->delete());

        foreach ($options as $o => $input) {
            $option = $input['id'] === null
                ? new ReviewFormFieldOption(['review_form_field_id' => $field->id])
                : $existing->get($input['id']) ?? self::notOwned("{$key}.{$o}.id");

            $option->fill(['label' => $input['label'], 'value' => $input['value'], 'order' => $o + 1])->save();
        }
    }

    /**
     * Id yang ada di tabel tapi milik form evaluasi atau field lain. Dulu baris itu dipindahkan
     * diam-diam ke form yang disunting, mencabutnya dari pemilik aslinya.
     *
     * @throws ValidationException
     */
    private static function notOwned(string $key): never
    {
        throw ValidationException::withMessages([
            $key => 'Data ini bukan milik form evaluasi yang sedang disunting. Muat ulang halaman lalu coba lagi.',
        ]);
    }
}
