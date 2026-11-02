<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * W58 — berkas biodata pendaftar yang dibaca Operator saat memeriksa NIDN. Satu definisi
 * "berkas biodata milik profil ini" dipakai daftar (tautan) dan unduhan (cakupan 404).
 */
final class BiodataFiles implements ChangesNoState
{
    /**
     * Berkas dari submission biodata terakhir tiap profil, sekali kueri untuk seluruh antrian.
     *
     * @param  Collection<int, UserProfile>  $profiles
     * @return array<int, list<array{label: string, url: string}>> per id profil
     */
    public static function forProfiles(Collection $profiles): array
    {
        $profileIdByUser = $profiles->mapWithKeys(fn (UserProfile $profile): array => [$profile->user_id => $profile->id]);

        if ($profileIdByUser->isEmpty()) {
            return [];
        }

        $latest = self::biodataSubmissions()
            ->whereIn('submitted_by', $profileIdByUser->keys()->all())
            ->selectRaw('max(id)')
            ->groupBy('submitted_by');

        $files = [];

        foreach (self::fileResponses()->whereIn('form_submission_id', $latest)->with(['formField:id,label', 'formSubmission:id,submitted_by'])->orderBy('id')->get() as $response) {
            $profileId = $profileIdByUser->get($response->formSubmission?->submitted_by);

            if ($profileId === null) {
                continue;
            }

            $files[$profileId][] = [
                'label' => $response->formField->label ?? 'Berkas',
                'url' => route('admin.verifications.files.show', [$profileId, $response->id]),
            ];
        }

        return $files;
    }

    public static function belongsTo(UserProfile $profile, FormFieldResponse $response): bool
    {
        return self::fileResponses()
            ->whereKey($response->id)
            ->whereIn('form_submission_id', self::biodataSubmissions()->where('submitted_by', $profile->user_id)->select('id'))
            ->exists();
    }

    /** @return Builder<FormSubmission> */
    private static function biodataSubmissions(): Builder
    {
        return FormSubmission::query()
            ->whereHas('form', fn (Builder $form) => $form->where('form_type_id', FormType::BIODATA_ID));
    }

    /** @return Builder<FormFieldResponse> */
    private static function fileResponses(): Builder
    {
        return FormFieldResponse::query()
            ->whereNotNull('value')
            ->whereHas('formField.fieldType', fn (Builder $type) => $type->where('name', 'file'));
    }
}
