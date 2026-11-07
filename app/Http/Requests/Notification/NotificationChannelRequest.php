<?php

namespace App\Http\Requests\Notification;

use App\Support\Notification\NotificationChannels;
use Illuminate\Foundation\Http\FormRequest;

/** ADMIN-08-10 — deklarasi channel satu tipe event. Wewenang dijaga `can:schemes.manage`. */
class NotificationChannelRequest extends FormRequest
{
    private const EMPTY_MESSAGE = 'Pilih minimal satu channel: in-app, email, atau keduanya.';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // BR-NOT-06, W113 — daftar kosong ditolak; Action ikut menegakkannya.
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:'.NotificationChannels::IN_APP.','.NotificationChannels::EMAIL],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['channels.required' => self::EMPTY_MESSAGE, 'channels.min' => self::EMPTY_MESSAGE];
    }

    /** @return list<string> */
    public function channels(): array
    {
        return array_map(
            fn (int|string $index): string => $this->string("channels.{$index}")->value(),
            array_keys($this->array('channels')),
        );
    }
}
