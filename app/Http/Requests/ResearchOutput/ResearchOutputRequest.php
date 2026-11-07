<?php

namespace App\Http\Requests\ResearchOutput;

use App\Data\Request\ResearchOutputInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * BR-RO-01..09, W141 — bentuk masukan luaran. Kelayakannya (tipe, metadata, berkas wajib)
 * milik `ResearchOutputGuard`, supaya jalur lain (impor, koreksi Operator) menegakkan hal sama.
 */
class ResearchOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'output_type' => 'required|string|max:64',
            'metadata' => 'nullable|array',
            // W141 — berkas sungguhan; path teks dari klien ditolak di sini.
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ];
    }

    public function toInput(): ResearchOutputInput
    {
        $metadata = [];

        foreach ($this->array('metadata') as $key => $value) {
            if (is_string($key)) {
                $metadata[$key] = $value;
            }
        }

        return new ResearchOutputInput($this->string('output_type')->value(), $metadata, $this->uploads());
    }

    /** @return list<UploadedFile> */
    private function uploads(): array
    {
        $files = $this->file('files');

        // `files.*` sudah divalidasi sebagai berkas di rules().
        return is_array($files) ? array_values($files) : [];
    }
}
