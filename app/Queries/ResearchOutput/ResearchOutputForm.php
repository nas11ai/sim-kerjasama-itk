<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\ResearchOutput\OutputFileData;
use App\Data\View\ResearchOutput\OutputFormData;
use App\Data\View\ResearchOutput\OutputTypeData;
use App\Data\View\ResearchOutput\OutputWriteAccessData;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use App\Models\User;
use App\Queries\Submission\RealisedOutputTypeChoices;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Props ResearchOutput/FormPage. Halaman yang tak boleh ditulisi dijawab 403, bukan halaman
 * denial: tombol menuju ke sini sudah tersembunyi lewat definisi yang sama (W146).
 *
 * @method static OutputFormData run(User $user, FormSubmission $submission)
 */
class ResearchOutputForm implements ChangesNoState
{
    use AsAction;

    public static function create(User $user, FormSubmission $submission): OutputFormData
    {
        return self::data($submission, self::authorize($user, $submission), null);
    }

    public static function edit(User $user, ResearchOutput $output): OutputFormData
    {
        $submission = $output->formSubmission;

        return self::data($submission, self::authorize($user, $submission), $output);
    }

    public function handle(User $user, FormSubmission $submission): OutputFormData
    {
        return self::create($user, $submission);
    }

    private static function authorize(User $user, FormSubmission $submission): OutputWriteAccessData
    {
        // BR-SM-07 lebih dulu: pengajuan yang tak terbaca juga tak bisa ditulisi.
        abort_unless($submission->isAccessibleBy($user), 403);

        $access = OutputWriteAccess::for($user, $submission);
        abort_unless($access->canWrite, 403, $access->blockedReason ?? '');

        return $access;
    }

    private static function data(FormSubmission $submission, OutputWriteAccessData $access, ?ResearchOutput $output): OutputFormData
    {
        // `submission_type_id` ikut: pilihan tipe membaca jenis pengajuan lewat skemanya (BR-RO-02).
        $submission->loadMissing(['form:id,title', 'scheme:id,name,submission_type_id']);

        return new OutputFormData(
            submissionId: $submission->id,
            submissionTitle: $submission->displayTitle(),
            scheme: $submission->scheme?->name,
            access: $access,
            types: self::types($submission, $output),
            outputId: $output?->id,
            outputType: $output?->output_type,
            metadata: $output->metadata ?? [],
            files: $output === null ? [] : OutputFileData::many($output),
        );
    }

    /**
     * BR-RO-02/05 — pilihan tipe. Saat sunting, tipe luaran itu tetap ditawarkan meski kini
     * nonaktif atau tak cocok jenis pengajuan (preseden W110), supaya isiannya tidak hilang.
     *
     * @return list<OutputTypeData>
     */
    private static function types(FormSubmission $submission, ?ResearchOutput $output): array
    {
        $types = RealisedOutputTypeChoices::run($submission);
        $offered = array_column($types, 'code');

        if ($output === null || in_array($output->output_type, $offered, true)) {
            return $types;
        }

        $current = OutputTypeDefinition::query()->where('code', $output->output_type)->first();

        return $current === null ? $types : [...$types, OutputTypeData::of($current)];
    }
}
