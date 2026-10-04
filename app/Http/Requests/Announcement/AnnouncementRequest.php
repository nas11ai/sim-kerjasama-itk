<?php

namespace App\Http\Requests\Announcement;

use App\Data\Request\AnnouncementInput;
use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:public,private',
            'expired_at' => 'nullable|date',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,png,pdf|max:2048',
            'deleted_files' => 'nullable|array',
            'deleted_files.*' => 'integer',
        ];
    }

    public function toInput(): AnnouncementInput
    {
        return new AnnouncementInput(
            title: $this->string('title')->value(),
            content: $this->string('content')->value(),
            type: $this->string('type')->value(),
            expiredAt: $this->filled('expired_at') ? $this->string('expired_at')->value() : null,
            files: array_values($this->file('files', [])),
            deletedFileIds: array_map(intval(...), $this->array('deleted_files')),
        );
    }
}
