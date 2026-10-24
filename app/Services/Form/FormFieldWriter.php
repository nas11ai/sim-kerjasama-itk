<?php

namespace App\Services\Form;

use App\Data\Request\FormFieldInput;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use Illuminate\Validation\ValidationException;

/**
 * Menyamakan field dan opsi sebuah Form dengan isi layar. Dipanggil dari dalam transaksi
 * CreateForm dan ReviseForm, yang menulis jejak auditnya.
 */
final class FormFieldWriter
{
    /**
     * @param  list<FormFieldInput>  $fields
     *
     * @throws ValidationException
     */
    public static function sync(Form $form, array $fields): void
    {
        $existing = $form->formFields()->get()->keyBy('id');
        $kept = array_filter(array_map(fn (FormFieldInput $field): ?int => $field->id, $fields));

        // BR-FE-12 — satu per satu lewat model: soft delete, jawaban lama tetap terbaca.
        $existing->except($kept)->each(fn (FormField $field) => $field->delete());

        foreach ($fields as $index => $input) {
            $field = $input->id === null
                ? new FormField(['form_id' => $form->id])
                : $existing->get($input->id) ?? self::notOwned("fields.{$index}.id");

            $field->fill([
                'field_type_id' => $input->fieldTypeId,
                'label' => $input->label,
                'is_required' => $input->isRequired,
                'order' => $index + 1,
            ])->save();

            // BR-FE-24 — opsi null berarti kiriman tak menyebut opsi: tidak ada perubahan.
            if ($input->options !== null) {
                self::syncOptions($field, $input->options, "fields.{$index}.options");
            }
        }
    }

    /**
     * @param  list<array{id: int|null, label: string}>  $options
     *
     * @throws ValidationException
     */
    private static function syncOptions(FormField $field, array $options, string $key): void
    {
        $existing = $field->formFieldOptions()->get()->keyBy('id');
        $kept = array_filter(array_column($options, 'id'));

        // BR-FE-24 — satu per satu, bukan hapus massal yang melewati SoftDeletes.
        $existing->except($kept)->each(fn (FormFieldOption $option) => $option->delete());

        foreach ($options as $o => $input) {
            $option = $input['id'] === null
                ? new FormFieldOption(['form_field_id' => $field->id])
                : $existing->get($input['id']) ?? self::notOwned("{$key}.{$o}.id");

            $option->fill(['label' => $input['label']])->save();
        }
    }

    /**
     * Id yang ada di tabel tapi milik form atau field lain. Dulu baris itu dipindahkan
     * diam-diam ke form yang disunting, mencabutnya dari pemilik aslinya.
     *
     * @throws ValidationException
     */
    private static function notOwned(string $key): never
    {
        throw ValidationException::withMessages([
            $key => 'Data ini bukan milik formulir yang sedang disunting. Muat ulang halaman lalu coba lagi.',
        ]);
    }
}
