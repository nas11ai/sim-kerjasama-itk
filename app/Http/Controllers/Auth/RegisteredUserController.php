<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\RegisterByInvitation;
use App\Actions\Identity\RegisterResearcher;
use App\Http\Controllers\Controller;
use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        $roles = Role::whereIn('id', [3, 4])
            ->select('id', 'name')
            ->get();

        $organizations = Organization::where('type', 'study_program')
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        return Inertia::render('Auth/RegisterPage', [
            'roles' => $roles,
            'studyPrograms' => $organizations,
        ]);
    }

    /**
     * BR-IAM-01, BR-IAM-02, BR-IAM-12 — registrasi mandiri dosen ITK.
     *
     * Cabang `app()->environment('testing')` yang dulu ada di sini dicabut:
     * ia membuat jalur yang diuji berbeda dari jalur yang berjalan, sehingga
     * test "new users can register" lulus dengan email `@example.com` yang
     * seharusnya ditolak BR-IAM-01. Test yang menguji jalur lain tidak
     * membuktikan apa pun tentang jalur yang sesungguhnya.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'nidn' => 'required|string|max:32',
            'organization_id' => ['required', Rule::exists('organizations', 'id')],
        ]);

        $user = RegisterResearcher::run([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'nidn' => $validated['nidn'],
            'organization_id' => (int) $validated['organization_id'],
        ]);

        event(new Registered($user));

        // BR-IAM-04 — akun `pending` boleh masuk. Kalau tidak, layar
        // "Menunggu Verifikasi" tidak akan pernah terbaca dan pendaftar
        // menghadapi jalan buntu selama verifikasi berjalan berhari-hari.
        Auth::login($user);

        return redirect()->route('dashboard');
    }

    /**
     * BR-IAM-06 — layar pendaratan sebuah tautan undangan.
     *
     * Dua layar, satu route. Aturannya sudah menyatakan bahwa "undangan yang
     * terlanjur dibuka mendarat di layar token tidak berlaku", dan tanpa GET
     * ini kalimat itu menggambarkan perilaku yang tidak pernah bisa terjadi:
     * yang ada hanya POST, jadi pendaftar tidak punya tempat mendarat sama
     * sekali.
     *
     * Token yang tidak dikenal diperlakukan sama dengan yang tidak berlaku —
     * membedakan keduanya memberi tahu penebak bahwa tebakannya mendekati.
     */
    public function createInvited(string $token): Response
    {
        $invitation = InvitationToken::where('token', $token)->first();

        if ($invitation === null || !$invitation->isValid()) {
            return Inertia::render('Auth/InvitationInvalidPage');
        }

        return Inertia::render('Auth/RegisterInvitationPage', [
            'token' => $token,
            'organization' => $invitation->organization?->name,
        ]);
    }

    /**
     * BR-IAM-06, BR-IAM-12 — registrasi lewat tautan undangan.
     *
     * Tidak dibatasi domain, dan akunnya aktif seketika: tidak ada NIDN untuk
     * diverifikasi, dan Operator sudah menyaring saat menerbitkan token.
     *
     * @throws ValidationException
     */
    public function storeInvited(Request $request, string $token): RedirectResponse
    {
        $invitation = InvitationToken::where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = RegisterByInvitation::run($invitation, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
