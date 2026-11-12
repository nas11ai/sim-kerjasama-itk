<?php

namespace App\Data\View\FormEngine;

use App\Models\Form;
use Spatie\LaravelData\Data;

/**
 * Satu baris ADMIN-08-05. Hitungan dimuat FormDirectory lewat `withCount`, jadi
 * membangun baris tidak menambah kueri.
 */
final class FormRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $typeName,
        public int $fieldCount,
        public int $activeSubmissionCount,
        public int $submissionCount,
        public int $stepCount,
        public bool $isActive,
        public bool $isPublished,
    ) {}

    public static function fromModel(Form $form): self
    {
        return new self(
            id: $form->id,
            title: $form->title,
            typeName: $form->formType->name ?? '',
            fieldCount: $form->field_count,
            activeSubmissionCount: $form->active_submission_count,
            submissionCount: $form->submission_count,
            // BR-FE-32 — langkah tahap terbit (versi mana pun) juga menghalangi hapus.
            stepCount: $form->step_count,
            isActive: $form->is_active,
            // BR-FE-34 — lencana "Belum terbit": Form ini belum bisa dipakai langkah tahap.
            isPublished: $form->published_at !== null,
        );
    }
}
