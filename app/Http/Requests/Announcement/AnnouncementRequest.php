<?php

namespace App\Http\Requests\Announcement;

use App\Data\Request\AnnouncementInput;
use App\Support\Announcement\AnnouncementAudience;
use App\Support\Announcement\AnnouncementHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

class AnnouncementRequest extends FormRequest
{
    private const EMPTY_CONTENT = 'Isi pengumuman wajib diisi.';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|list<string|In>> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:50000',
            'type' => 'required|in:public,private',
            // fe-v2-design §4.7 — publik tidak boleh bertarget; hanya lima peran DDD.
            'target_roles' => ['array', 'prohibited_if:type,public'],
            'target_roles.*' => ['string', 'distinct', Rule::in(array_keys(AnnouncementAudience::ROLE_LABELS))],
            'expired_at' => 'nullable|date_format:Y-m-d',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,png,pdf|max:2048',
            'deleted_files' => 'nullable|array',
            'deleted_files.*' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'content.required' => self::EMPTY_CONTENT,
            'target_roles.prohibited_if' => 'Pengumuman publik tidak bisa dibatasi per peran.',
        ];
    }

    /**
     * fe-v2-design §4.7 K6 — `<p></p>` (keadaan awal editor) lolos `required`, jadi isi
     * diperiksa dari teks polosnya.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('content') || !is_string($this->input('content'))) {
                    return;
                }

                if (AnnouncementHtml::plainText($this->string('content')->value()) === '') {
                    $validator->errors()->add('content', self::EMPTY_CONTENT);
                }
            },
        ];
    }

    public function toInput(): AnnouncementInput
    {
        $type = $this->string('type')->value();

        return new AnnouncementInput(
            title: $this->string('title')->value(),
            content: $this->string('content')->value(),
            type: $type,
            // W34 — tanggal saja; berlaku sampai akhir hari itu.
            expiredAt: $this->filled('expired_at') ? $this->string('expired_at')->value().' 23:59:59' : null,
            targetRoles: $type === 'public' ? [] : array_values(array_filter($this->array('target_roles'), is_string(...))),
            files: array_values($this->file('files', [])),
            deletedFileIds: array_map(intval(...), $this->array('deleted_files')),
        );
    }
}
