<?php

namespace App\Http\Requests\Identity;

use App\Data\Request\BiodataInput;
use App\Http\Requests\Concerns\ReadsFieldAnswers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class BiodataRequest extends FormRequest
{
    use ReadsFieldAnswers;

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
        return new BiodataInput(
            formId: $this->integer('form_id'),
            isSubmitted: $this->boolean('is_submitted'),
            responses: $this->fieldAnswers(),
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
