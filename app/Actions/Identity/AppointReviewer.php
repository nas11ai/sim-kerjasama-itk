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
 *
 * Penunjukan bukan pembuatan akun: tidak ada jalur registrasi mandiri untuk
 * reviewer. Konsekuensinya, pakar dari luar ITK harus diundang lebih dulu
 * lewat jalur undangan (BR-IAM-12).
 *
 * **Idempoten per orang.** Menunjuk ulang memperbarui record yang ada, bukan
 * membuat yang kedua: dua record untuk satu orang membuat penghitungan beban
 * (BR-REV-04) dan keterlihatan skor (BR-REV-11) membaca dari himpunan yang
 * ambigu — dan itu tidak gagal mencolok, ia cuma menghitung dua kali.
 *
 * Mengubah tipe **mencabut role tipe lama**. Menambah role baru tanpa
 * mencabut yang lama membuat reviewer eksternal tetap bisa melihat skor
 * rekannya: salah di M2, tanpa ada yang gagal di sini.
 *
 * @method static Reviewer run(User $user, string $type, ?string $startDate = null, ?string $endDate = null)
 */
class AppointReviewer
{
    use AsAction;

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_EXTERNAL = 'external';

    /** Peta tipe → role Spatie yang dipakai BR-REV-11 membatasi skor. */
    private const ROLES = [
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
    }

    private function persist(User $user, string $type, ?string $startDate, ?string $endDate): Reviewer
    {
        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Cabut role tipe lain lebih dulu, lalu berikan yang benar.
        $user->removeRole(self::ROLES[$type === self::TYPE_INTERNAL ? self::TYPE_EXTERNAL : self::TYPE_INTERNAL]);
        $user->assignRole(self::ROLES[$type]);

        $existing = Reviewer::query()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            // Menunjuk ulang tanpa menyebut tanggal tidak menghapus masa
            // tugas yang sedang berjalan — yang diperbarui hanya yang
            // benar-benar dikirim.
            $existing->update(array_filter([
                'reviewer_type' => $type,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ], static fn ($value): bool => $value !== null));

            return $existing->refresh();
        }

        // Masa tugas wajib ada — BR-REV-09 menegakkannya saat penugasan,
        // dan reviewer tanpa masa tugas tidak akan pernah bisa ditugaskan.
        // Default satu tahun sejak hari ini adalah masa jabatan yang lazim
        // di LPPM, dan Operator tetap bisa menyuntingnya.
        return Reviewer::create([
            'user_id' => $user->id,
            'reviewer_type' => $type,
            'start_date' => $startDate ?? Carbon::today()->toDateString(),
            'end_date' => $endDate ?? Carbon::today()->addYear()->toDateString(),
        ]);
    }
}
