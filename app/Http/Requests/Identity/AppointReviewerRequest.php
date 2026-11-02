<?php

namespace App\Http\Requests\Identity;

use Illuminate\Validation\Rules\In;

/** BR-IAM-11 — penunjukan memilih user yang sudah ada (IAM-REVIEWER-03), bukan membuat akun. */
class AppointReviewerRequest extends ReviseAppointmentRequest
{
    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            ...parent::rules(),
        ];
    }

    public function userId(): int
    {
        return $this->integer('user_id');
    }
}
