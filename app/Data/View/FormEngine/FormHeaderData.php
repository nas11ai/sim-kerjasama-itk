<?php

namespace App\Data\View\FormEngine;

use App\Models\Form;
use Spatie\LaravelData\Data;

/** Kepala halaman sunting Form (ADMIN-08-06/07): judul dan badge status di setiap tab. */
final class FormHeaderData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public bool $isActive,
    ) {}

    public static function fromModel(Form $form): self
    {
        return new self(id: $form->id, title: $form->title, isActive: $form->is_active);
    }
}
