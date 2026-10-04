<?php

namespace App\Actions\Identity;

use App\Models\Reviewer;
use App\Models\User;
use App\Notifications\ReviewerAppointedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Role;

/**
 * BR-IAM-11 — Operator menunjuk reviewer dari user yang **sudah terdaftar**.
 * Tak ada registrasi mandiri reviewer: pakar luar ITK diundang lewat jalur
 * undangan (BR-IAM-12). Idempoten; mengubah tipe mencabut role lama.
 *
 * @method static Reviewer run(User $user, string $type, ?string $startDate = null, ?string $endDate = null)
 */
class AppointReviewer
{
    use AsAction;

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_EXTERNAL = 'external';

    /** Peta tipe → role Spatie yang dipakai BR-REV-11 membatasi skor. */
    public const ROLES = [
        self::TYPE_INTERNAL => 'reviewer_internal',
        self::TYPE_EXTERNAL => 'reviewer_external',
    ];

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $type, ?string $startDate = null, ?string $endDate = null): Reviewer
    {
        $this->guardAppointable($user, $type);

        $reviewer = DB::transaction(fn (): Reviewer => $this->persist($user, $type, $startDate, $endDate));

        // BR-IAM-15 — yang ditunjuk diberi tahu; kemampuannya berubah tanpa ia
        // melakukan apa pun.
        Deliver::to($user, new ReviewerAppointedNotification($type));

        $tercatat = $reviewer;

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Reviewer ditunjuk', $tercatat);

        return $tercatat;
    }

    /**
     * @throws ValidationException
     */
    private function guardAppointable(User $user, string $type): void
    {
        if (!array_key_exists($type, self::ROLES)) {
            throw ValidationException::withMessages([
                'reviewer_type' => 'Tipe reviewer harus internal atau eksternal.',
            ]);
        }

        // BR-IAM-05 — akun nonaktif tidak menerima penugasan baru, dan
        // penunjukan adalah bentuk paling awal dari itu.
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun ini nonaktif dan tidak dapat ditunjuk sebagai reviewer.',
            ]);
        }

        // BR-IAM-11 — satu record per orang. persist() memilih record pertama, jadi
        // menunjuk ulang pemilik duplikat mengubah baris yang salah: tolak sampai dirapikan.
        if (Reviewer::where('user_id', $user->id)->count() > 1) {
            throw ValidationException::withMessages([
                'user_id' => 'User ini punya lebih dari satu record reviewer; rapikan duplikatnya dulu.',
            ]);
        }
    }

    private function persist(User $user, string $type, ?string $startDate, ?string $endDate): Reviewer
    {
        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Cabut role tipe lain lebih dulu, lalu berikan yang benar. Menambah
        // tanpa mencabut membuat reviewer eksternal tetap bisa melihat skor
        // rekannya: salah di M2, tanpa ada yang gagal di sini.
        $user->removeRole(self::ROLES[$type === self::TYPE_INTERNAL ? self::TYPE_EXTERNAL : self::TYPE_INTERNAL]);
        $user->assignRole(self::ROLES[$type]);

        // Idempoten: menunjuk ulang memperbarui, bukan membuat record kedua.
        // Dua record membuat beban (BR-REV-04) dan keterlihatan skor
        // (BR-REV-11) dihitung dari himpunan ambigu, tanpa gagal mencolok.
        $existing = Reviewer::query()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            $existing->update(['reviewer_type' => $type] + $this->tenureFor($existing, $startDate, $endDate));

            return $existing->refresh();
        }

        return Reviewer::create([
            'user_id' => $user->id,
            'reviewer_type' => $type,
        ] + $this->defaultTenure($startDate, $endDate));
    }

    /**
     * Menunjuk ulang tanpa menyebut tanggal tidak menghapus masa tugas yang sedang
     * berjalan — yang diperbarui hanya yang benar-benar dikirim. Record yang di luar masa
     * tugas mendapat default seperti record baru; tanpa itu ia tetap tak bisa ditugaskan.
     *
     * @return array<string, string>
     */
    private function tenureFor(Reviewer $existing, ?string $startDate, ?string $endDate): array
    {
        if (!$existing->isWithinTenure()) {
            return $this->defaultTenure($startDate, $endDate);
        }

        return array_filter(
            ['start_date' => $startDate, 'end_date' => $endDate],
            static fn (?string $value): bool => $value !== null,
        );
    }

    /**
     * Masa tugas wajib ada (BR-REV-09 menegakkannya saat penugasan). Default satu tahun
     * sejak hari ini adalah masa jabatan lazim di LPPM; Operator tetap bisa menyuntingnya.
     *
     * @return array{start_date: string, end_date: string}
     */
    private function defaultTenure(?string $startDate, ?string $endDate): array
    {
        return [
            'start_date' => $startDate ?? Carbon::today()->toDateString(),
            'end_date' => $endDate ?? Carbon::today()->addYear()->toDateString(),
        ];
    }
}
