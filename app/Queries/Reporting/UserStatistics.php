<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-06 — hitungan user untuk layar statistik, dihitung langsung saat dibaca.
 * "User Fakultas" adalah user yang unitnya fakultas mana pun atau berada di bawahnya;
 * "User Program Studi" adalah user yang unitnya bertipe study_program.
 *
 * @method static array{userRecent: Collection<int, User>, totalUsers: int, totalAdmin: int, totalNonAdmin: int, totalProdi: int, totalFaculty: int} run()
 */
class UserStatistics implements ChangesNoState
{
    use AsAction;

    /** @return array{userRecent: Collection<int, User>, totalUsers: int, totalAdmin: int, totalNonAdmin: int, totalProdi: int, totalFaculty: int} */
    public function handle(): array
    {
        return [
            'userRecent' => User::where('created_at', '>=', now()->subHours(24))
                ->select('id', 'name', 'email', 'created_at')
                ->get(),
            'totalUsers' => User::count(),
            'totalAdmin' => User::role('Admin')->count(),
            'totalNonAdmin' => User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'Admin');
            })->count(),
            'totalProdi' => $this->usersInStudyPrograms(),
            'totalFaculty' => $this->usersUnderFaculties(),
        ];
    }

    private function usersInStudyPrograms(): int
    {
        return UserProfile::join('organizations', 'organizations.id', '=', 'user_profiles.organization_id')
            ->where('organizations.type', 'study_program')
            ->distinct()
            ->count('user_profiles.user_id');
    }

    /** Menurut posisi unitnya di pohon, aktif atau tidak — lihat UnitFaculty. */
    private function usersUnderFaculties(): int
    {
        return UserProfile::joinSub(UnitFaculty::run(), 'unit_faculty', 'unit_faculty.unit_id', '=', 'user_profiles.organization_id')
            ->distinct()
            ->count('user_profiles.user_id');
    }
}
