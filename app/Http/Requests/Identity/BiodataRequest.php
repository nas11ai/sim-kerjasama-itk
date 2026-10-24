<?php

namespace App\Http\Requests\Identity;

use App\Data\Request\BiodataInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class BiodataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'form_id' => 'required|exists:forms,id',
            'is_submitted' => 'required|boolean',
            'responses' => 'required|array',
            'responses.*.form_field_id' => 'required|exists:form_fields,id',
            'responses.*.value' => 'nullable',
            'file_uploads' => 'nullable|array',
            'file_uploads.*' => 'file|max:10240',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['file_uploads.*.max' => 'Ukuran file terlalu besar. Maksimal 10MB.'];
    }

    public function toInput(): BiodataInput
    {
        $responses = [];

        foreach (array_keys($this->array('responses')) as $index) {
            $fieldId = $this->integer("responses.{$index}.form_field_id");
            $value = $this->input("responses.{$index}.value");

            // Kosong (null, '', false, []) jadi null; larik disimpan sebagai JSON.
            $responses[$fieldId] = match (true) {
                $value === null, $value === '', $value === false, $value === [] => null,
                is_array($value) => json_encode($value) ?: null,
                is_scalar($value) => (string) $value,
                default => null,
            };
        }

        return new BiodataInput(
            formId: $this->integer('form_id'),
            isSubmitted: $this->boolean('is_submitted'),
            responses: $responses,
            uploads: $this->uploads(),
        );
    }

    /** @return array<int, UploadedFile> */
    private function uploads(): array
    {
        $files = $this->file('file_uploads');
        $uploads = [];

        foreach (is_array($files) ? $files : [] as $fieldId => $file) {
            if ($file->isValid()) {
                $uploads[(int) $fieldId] = $file;
            }
        }

        return $uploads;
    }
}
