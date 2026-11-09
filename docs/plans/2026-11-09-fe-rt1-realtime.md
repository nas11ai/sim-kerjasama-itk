# FE v2 — Irisan RT1: Realtime — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tab yang sedang terbuka langsung keluar begitu akunnya dinonaktifkan atau seluruh sesinya diakhiri. Badge "Sedang aktif" di layar user berubah tanpa reload. Arti "sedang aktif" tetap BR-IAM-21: ada aktivitas sesi dalam 5 menit terakhir.

**Architecture:** Laravel Reverb sebagai server WebSocket, `laravel-echo` + `pusher-js` sebagai klien. Ada dua kanal privat dan **tidak ada kanal presence** (W168):
- `App.Models.User.{id}` menerima `sessions.ended` lalu memindahkan klien ke halaman login.
- `identity.activity` hanya untuk pemegang `users.verify`/`users.manage`. Kanal ini menerima `session.active` paling sering sekali per menit per user, dikirim dari middleware setiap kali sesi beraktivitas.

Penegakan yang mengikat tetap di server (`EnsureAccountActive`, baris `sessions` dihapus, `remember_token` diputar). Dorongan realtime hanya mempercepat apa yang dilihat user. Karena itu semua broadcast **diantrekan**: Reverb atau worker yang mati tidak boleh menggagalkan deaktivasi.

**Tech Stack:** Laravel 13 / PHP 8.4, `laravel/reverb` ^1.12 (mendukung illuminate ^13, diperiksa 2026-11-09), `laravel-echo` ^2.6, `pusher-js` ^8.6, Inertia v3 + Vue 3 + TS, Pest, Vitest, Playwright.

**Spec:** `docs/design/fe-v2-design.md` baris RT1 di §8, §9 K7 dan W60. DDD `ddd/generic/02_identity_access.md` BR-IAM-05, BR-IAM-21. BDD `generic/identity/session.feature` [IAM-SESSION-01..03], `generic/identity/deactivation.feature` [IAM-DEACT-09]. `AGENTS.md`.

**Ukuran:** irisan sedang. Rencana ditulis di session ini, lalu dijalankan dalam satu session (Task 1–6).

## Global Constraints

- Gate di setiap commit: `composer run quality` dan `npm run quality`, lewat container `simpas-ci:8.4` dengan `-e DB_PORT=55432 -e DB_PASSWORD=postgres` (PHP host 8.5).
- WSL bisa crash saat gate: cek `free -m` (≥ 3 GB) dulu. Jalankan pint, tiap tier phpstan, pest, npm quality, dan Playwright **satu per satu**, masing-masing di bawah watchdog yang mematikan proses bila `MemAvailable` < 500 MB. Vitest dengan `--maxWorkers=1`.
- Worktree butuh salinan `vendor/` sungguhan (`cp -a`) dan `.env`. Symlink `vendor` membuat test menjalankan kode checkout utama. `node_modules` di worktree adalah symlink ke checkout utama, jadi `npm install` paket baru memakai `--package-lock-only` di worktree. Setelah merge, jalankan `npm ci` di checkout utama.
- Dependency baru **hanya** tiga ini: `laravel/reverb`, `laravel-echo`, `pusher-js`. Tidak ada `@laravel/echo-vue`. Dependency lain tidak naik atau turun versi.
- `pending.txt` dan `uncovered.txt` tetap `max: 0`. `quality-legacy.txt` tidak bertambah.
- Tanpa `@phpstan-ignore`, baseline, cast, inline `@var`, atau `app()->environment('testing')`. Tanpa `any`/`console.log` di TS.
- Commit satu baris berbahasa Indonesia (`feat(fe): …`, `feat: …`, `chore: …`), tanpa trailer.
- `resources/js/ziggy.js` sudah basi sejak S2b. **Jangan diregenerasi.**
- Jangan menyunting `docs/requirement-analysis/ddd/`. Rencana ini tidak mengubah BR apa pun.

## Keputusan penulis rencana (dicatat ke §9 di Task 6)

- **W168 — Tanpa kanal presence.** Baris RT1 di §8 menyebut "kanal presence menggantikan pembacaan kehadiran `SessionRegistry`". Itu bertentangan dengan dua hal:
  1. BR-IAM-21 dan [IAM-SESSION-02] mengikat "sedang aktif = aktivitas sesi ≤ 5 menit, diturunkan dari sesi". Keanggotaan presence mengukur koneksi WebSocket, bukan aktivitas sesi. Presence juga tidak punya "terakhir aktif" dan tidak bisa memfilter daftar user di server.
  2. Daftar anggota kanal presence terlihat oleh **setiap anggotanya**. Bila semua user bergabung, peneliti bisa membaca siapa yang online lewat devtools, padahal [IAM-SESSION-03] melarangnya.

  **Pemilik, 2026-11-09: opsi (a) disetujui.** `SessionRegistry` tetap sumber kehadiran (render awal, filter "Sedang aktif", "terakhir aktif"). RT1 hanya menambahkan dorongan `session.active` lewat kanal privat yang otorisasinya sama dengan `UserPolicy::viewAny` (W47). Klien menurunkan `online` dari `lastActiveAt` dengan jendela yang sama (5 menit), sehingga badge padam sendiri tanpa request.
- **W169 — Broadcast diantrekan, bukan `ShouldBroadcastNow`.** `ShouldBroadcastNow` memanggil Reverb di dalam request. Kalau Reverb mati, request itu gagal: deaktivasi sudah tersimpan, tetapi admin melihat 500, dan setiap request user aktif ikut gagal. Kedua event memakai `ShouldBroadcast`, dan `UserSessionsEnded` juga `ShouldDispatchAfterCommit`. Akibatnya jeda logout ≈ jeda worker antrean (driver `database`, beberapa detik). Syarat deploy: proses `php artisan reverb:start`, worker antrean, proxy WebSocket ke port Reverb, dan variabel `REVERB_*`/`VITE_REVERB_*`.
- **W170 — Pesan di halaman login.** Klien yang menerima `sessions.ended` melakukan muat ulang penuh ke `route('login', { sesi: <reason> })`. Muat ulang penuh dipilih dengan sengaja: koneksi Echo ikut putus dan data sensitif di layar hilang. `AuthenticatedSessionController::create` memetakan `?sesi=` lewat enum `SessionEndReason` ke prop `notice`. Nilai yang tak dikenal menjadi `null`. Teksnya tetap, jadi memalsukan query hanya menampilkan salah satu dari dua kalimat itu. `EnsureAccountActive` memakai teks enum yang sama (Replace Magic Literal with Constant).

## Review Focus

1. **Reverb atau worker mati saat admin menonaktifkan user** → deaktivasi tetap berhasil dengan flash sukses; logout ditegakkan di request berikutnya ([IAM-DEACT-09]). Dipatok di Task 2 (event `ShouldBroadcast`, bukan `ShouldBroadcastNow`, dan dispatch setelah commit).
2. **Build tanpa `VITE_REVERB_APP_KEY`** (CI, dev tanpa Reverb, test) → aplikasi berjalan normal tanpa error dan tanpa koneksi. Dipatok di Task 1 (`echo()` mengembalikan `null`) dan Task 3/5 (konsumen no-op).
3. **Peneliti berlangganan `identity.activity`** → ditolak ([IAM-SESSION-03]). Dipatok di Task 1.
4. **User berlangganan kanal privat user lain** → ditolak. Dipatok di Task 1.
5. **Logout lalu login sebagai user lain tanpa muat ulang** (Inertia) → langganan pindah ke user baru dan langganan lama ditinggalkan; tab tidak menerima `sessions.ended` milik orang lain. Dipatok di Task 3.

---

### Task 1: Infrastruktur broadcasting dan otorisasi kanal

**Files:**
- Modify: `composer.json`, `composer.lock`, `package.json`, `package-lock.json`
- Create: `config/broadcasting.php`, `config/reverb.php` (dari installer)
- Create: `routes/channels.php`
- Create: `app/Broadcasting/UserChannel.php`, `app/Broadcasting/IdentityActivityChannel.php`
- Create: `resources/js/lib/echo.ts`
- Modify: `bootstrap/app.php`, `.env.example`, `phpunit.xml`, `docker-compose.yml`, `resources/js/types/vite-env.d.ts`
- Test: `tests/Feature/Identity/BroadcastChannelTest.php`, `tests/frontend/lib/echo.spec.ts`

**Interfaces:**
- Produces: `UserChannel::join(User $user, int $id): bool`; `IdentityActivityChannel::join(User $user): bool`; nama kanal `App.Models.User.{id}` dan `identity.activity`; `echo(): Echo<'reverb'> | null` dari `@/lib/echo`.

- [ ] **Step 1: Pasang paket.** Di container PHP 8.4: `composer require laravel/reverb:^1.12`, lalu `php artisan install:broadcasting --reverb --without-node` (bila opsi itu tidak ada di versi terpasang, jalankan interaktif dan tolak pemasangan Node). Pertahankan `config/broadcasting.php`, `config/reverb.php`, dan `withBroadcasting(__DIR__.'/../routes/channels.php')` di `bootstrap/app.php`. Hapus `resources/js/echo.js` bila dibuat, dan kembalikan `resources/js/bootstrap.ts` bila disentuh. Untuk npm: `npm install --package-lock-only laravel-echo@^2.6 pusher-js@^8.6`.

- [ ] **Step 2: Env dan proses dev.**
  - `.env.example`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST=localhost`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`, dan `VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`.
  - `phpunit.xml`: `<env name="BROADCAST_CONNECTION" value="null"/>`, supaya test tidak pernah membuka koneksi.
  - Script `dev` di `composer.json`: tambahkan `"php artisan reverb:start"` dengan nama `reverb` dan warna kelima.
  - `docker-compose.yml`: service `reverb`, dengan image build yang sama dengan `app`, `command: php artisan reverb:start --host=0.0.0.0 --port=8080`, port `8080:8080`, dan `depends_on` yang sama dengan `app`.
  - `vite-env.d.ts`: empat `VITE_REVERB_*` sebagai `string | undefined`.

- [ ] **Step 3: Tulis test kanal yang gagal** di `tests/Feature/Identity/BroadcastChannelTest.php`:

```php
it('kanal privat user hanya untuk pemiliknya', function () {
    $sari = User::factory()->create();
    $budi = User::factory()->create();
    expect((new UserChannel)->join($sari, $sari->id))->toBeTrue()
        ->and((new UserChannel)->join($sari, $budi->id))->toBeFalse();
});

it('[IAM-SESSION-03] kanal aktivitas sesi hanya untuk users.verify atau users.manage', function () {
    // sessionViewer() dari UserSessionTest; pindahkan ke tests/Support bila perlu dipakai bersama
    expect((new IdentityActivityChannel)->join(sessionViewer('users.verify')))->toBeTrue()
        ->and((new IdentityActivityChannel)->join(sessionViewer('users.manage')))->toBeTrue()
        ->and((new IdentityActivityChannel)->join(User::factory()->create()))->toBeFalse();
});

it('routes/channels.php mendaftarkan kedua kanal', function () {
    expect(Broadcast::driver()->getChannels()->keys()->all())
        ->toContain('App.Models.User.{id}', 'identity.activity');
});
```

- [ ] **Step 4: Jalankan, pastikan gagal** (`./vendor/bin/pest tests/Feature/Identity/BroadcastChannelTest.php` lewat container). Yang diharapkan: kelas tidak ditemukan.

- [ ] **Step 5: Implementasi.** `UserChannel::join` = `$user->id === $id`. `IdentityActivityChannel::join` = `$user->can('viewAny', User::class)` (komentar `// BR-IAM-21, W47, IAM-SESSION-03`). Daftarkan di `routes/channels.php` dengan `Broadcast::channel('<nama>', <Kelas>::class)`. Route `/broadcasting/auth` berada di grup `web`, jadi `EnsureAccountActive` sudah menolak akun nonaktif tanpa cek tambahan.

- [ ] **Step 6: Test Vitest yang gagal** di `tests/frontend/lib/echo.spec.ts`: dengan `vi.stubEnv('VITE_REVERB_APP_KEY', '')`, `echo()` mengembalikan `null` dan tidak membuat instance Pusher. Dengan kunci terisi, `echo()` mengembalikan instance yang sama di dua panggilan. `pusher-js` di-mock.

- [ ] **Step 7: Implementasi `resources/js/lib/echo.ts`.** Instance dibuat lazy, satu per modul. Konfigurasinya: `broadcaster: 'reverb'`, `key`, `wsHost`, `wsPort`/`wssPort` dari `VITE_REVERB_PORT` (bawaan 80/443), `forceTLS: scheme === 'https'`, `enabledTransports: ['ws', 'wss']`. Konstruktor Pusher diberikan lewat opsi `Pusher` bila tipe laravel-echo 2.x mengizinkan. Kalau tidak, pakai `window.Pusher` dengan deklarasi di `types/global.d.ts`.

- [ ] **Step 8: Jalankan kedua test** dan pastikan lulus. Lalu gate penuh.

- [ ] **Step 9: Commit** — `chore: Reverb dan otorisasi kanal untuk RT1`

---

### Task 2: Event `UserSessionsEnded` dan pesan di halaman login

**Files:**
- Create: `app/Enums/SessionEndReason.php`, `app/Events/Identity/UserSessionsEnded.php`
- Modify: `app/Actions/FormSubmission/DeactivateUser.php`, `app/Actions/Identity/EndUserSessions.php`, `app/Http/Middleware/EnsureAccountActive.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `resources/js/types/auth.ts` (`LoginPageProps.notice: string | null`), `resources/js/Pages/Auth/LoginPage.vue`
- Test: `tests/Feature/Identity/SessionEndPushTest.php`, `tests/frontend/pages/LoginPage.spec.ts` (sudah ada, ditambah)

**Interfaces:**
- Produces: `enum SessionEndReason: string { case Deactivated = 'nonaktif'; case Ended = 'diakhiri'; public function notice(): string }`; event `UserSessionsEnded(int $userId, SessionEndReason $reason)` di `private-App.Models.User.{id}`, `broadcastAs() = 'sessions.ended'`, `broadcastWith() = ['reason' => 'nonaktif'|'diakhiri']`; prop login `notice`.

- [ ] **Step 1: Tulis test Pest yang gagal:**

```php
it('[IAM-DEACT-09] deaktivasi mendorong sessions.ended ke kanal user itu', function () {
    Event::fake([UserSessionsEnded::class]);
    // aktor users.manage + target tanpa pengajuan aktif, pola DeactivationEffectTest
    $this->actingAs($admin)->post(route('admin.users.deactivate', $sari))->assertRedirect();
    Event::assertDispatched(UserSessionsEnded::class,
        fn ($e) => $e->userId === $sari->id && $e->reason === SessionEndReason::Deactivated);
});

it('deaktivasi yang ditolak karena pengajuan aktif tidak mendorong apa pun', function () {
    // target lead researcher dengan pengajuan aktif → 422/redirect error
    Event::assertNotDispatched(UserSessionsEnded::class);
});

it('[IAM-SESSION-01] mengakhiri sesi mendorong sessions.ended dengan alasan diakhiri', function () {
    // route admin.users.sessions.end → reason Ended
});

it('bentuk siaran sessions.ended', function () {
    $e = new UserSessionsEnded(7, SessionEndReason::Ended);
    expect($e->broadcastOn()->name)->toBe('private-App.Models.User.7')
        ->and($e->broadcastAs())->toBe('sessions.ended')
        ->and($e->broadcastWith())->toBe(['reason' => 'diakhiri'])
        ->and($e)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($e)->not->toBeInstanceOf(ShouldBroadcastNow::class)   // W169, Review Focus 1
        ->and($e)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('halaman login menampilkan alasan sesi berakhir dari query', function (string $sesi, ?string $notice) {
    $this->get(route('login', ['sesi' => $sesi]))
        ->assertInertia(fn (Assert $p) => $p->where('notice', $notice));
})->with([
    ['nonaktif', 'Akun Anda dinonaktifkan. Hubungi LPPM bila ini keliru.'],
    ['diakhiri', 'Sesi Anda diakhiri oleh admin. Silakan login lagi.'],
    ['palsu', null],
]);
```

- [ ] **Step 2: Jalankan, pastikan gagal.**

- [ ] **Step 3: Implementasi.**
  - Enum `SessionEndReason` beserta `notice()`, berisi dua kalimat di atas persis.
  - Event (`ShouldBroadcast`, `ShouldDispatchAfterCommit`, `Dispatchable`; konstruktor `public readonly int $userId, public readonly SessionEndReason $reason`).
  - `DeactivateUser` dan `EndUserSessions` men-dispatch event itu tepat setelah baris `sessions` dihapus (`// BR-IAM-21, K7 — dorongan; penegakan tetap di server`).
  - `EnsureAccountActive` memakai `SessionEndReason::Deactivated->notice()`.
  - `create(Request $request)` mengisi `'notice' => SessionEndReason::tryFrom($request->string('sesi')->toString())?->notice()`.
  - `LoginPage.vue`: `<AppBanner v-if="notice" tone="denial">` di atas banner `status`.

- [ ] **Step 4: Vitest LoginPage**: prop `notice` merender teksnya di AppBanner, dan `null` tidak merender banner itu. Jalankan Pest dan Vitest, pastikan lulus, lalu gate penuh.

- [ ] **Step 5: Commit** — `feat: deaktivasi dan akhiri sesi mendorong sessions.ended`

---

### Task 3: Klien keluar seketika

**Files:**
- Create: `resources/js/lib/accountChannel.ts`
- Modify: `resources/js/app.ts`
- Test: `tests/frontend/lib/accountChannel.spec.ts`

**Interfaces:**
- Consumes: `echo()` (Task 1), event `.sessions.ended` dengan payload `{ reason: string }` (Task 2).
- Produces: `syncAccountChannel(userId: number | null): void`.

- [ ] **Step 1: Test Vitest yang gagal.** `@/lib/echo` di-mock menjadi objek palsu `{ private: vi.fn(() => ({ listen })), leave: vi.fn() }`, dan `route` global di-mock:
  - `syncAccountChannel(7)` → `private('App.Models.User.7')` sekali, dengan `listen('.sessions.ended', …)`.
  - `syncAccountChannel(7)` lagi → tidak ada langganan kedua (idempoten).
  - `syncAccountChannel(9)` → `leave('App.Models.User.7')` lalu `private('App.Models.User.9')` (Review Focus 5).
  - `syncAccountChannel(null)` → `leave('App.Models.User.9')`.
  - Memicu listener dengan `{ reason: 'nonaktif' }` → `window.location.assign` dipanggil dengan `route('login', { sesi: 'nonaktif' })`.
  - `echo()` mengembalikan `null` → tidak ada error dan tidak ada panggilan (Review Focus 2).

- [ ] **Step 2: Jalankan, pastikan gagal.**

- [ ] **Step 3: Implementasi** `syncAccountChannel` dengan state modul `currentId: number | null`. Di `app.ts`, panggil dengan `props.initialPage.props.auth?.user?.id ?? null` di `setup`, dan di `router.on('navigate', e => …)` dengan `e.detail.page.props`. Shared props `auth.user` boleh `null` (W162). Kanal ini dipasang di level aplikasi, bukan di `AppLayout`, supaya tidak langganan ulang di setiap kunjungan Inertia.

- [ ] **Step 4: Jalankan test, pastikan lulus**, lalu `npm run quality`.

- [ ] **Step 5: Commit** — `feat(fe): tab keluar seketika saat sesi diakhiri`

---

### Task 4: Dorongan aktivitas sesi

**Files:**
- Create: `app/Events/Identity/UserSessionActive.php`, `app/Http/Middleware/BroadcastSessionActivity.php`
- Modify: `bootstrap/app.php` (append setelah `EnsureAccountActive`), `app/Support/Identity/SessionRegistry.php` (docblock: kehadiran tetap di sini; RT1 hanya menambah dorongan, W168)
- Test: `tests/Feature/Identity/SessionActivityBroadcastTest.php`

**Interfaces:**
- Produces: event `UserSessionActive(int $userId, CarbonImmutable $at)` di `private-identity.activity`, `broadcastAs() = 'session.active'`, `broadcastWith() = ['userId' => int, 'at' => ISO-8601]`; `BroadcastSessionActivity::THROTTLE_SECONDS = 60`.

- [ ] **Step 1: Test yang gagal:**

```php
it('request user aktif mendorong session.active, paling sering sekali per menit', function () {
    Event::fake([UserSessionActive::class]);
    $sari = User::factory()->create(['is_active' => true]);
    $this->actingAs($sari)->get(route('dashboard'));
    $this->actingAs($sari)->get(route('dashboard'));
    Event::assertDispatchedTimes(UserSessionActive::class, 1);
    $this->travel(61)->seconds();
    $this->actingAs($sari)->get(route('dashboard'));
    Event::assertDispatchedTimes(UserSessionActive::class, 2);
});

it('tamu dan akun nonaktif tidak mendorong session.active', function () { /* GET login sebagai tamu; GET dashboard sebagai is_active=false */ });

it('bentuk siaran session.active', function () {
    $at = CarbonImmutable::parse('2026-11-09 10:00:00');
    $e = new UserSessionActive(7, $at);
    expect($e->broadcastOn()->name)->toBe('private-identity.activity')
        ->and($e->broadcastAs())->toBe('session.active')
        ->and($e->broadcastWith())->toBe(['userId' => 7, 'at' => $at->toIso8601String()])
        ->and($e)->not->toBeInstanceOf(ShouldBroadcastNow::class);
});
```

- [ ] **Step 2: Jalankan, pastikan gagal.**

- [ ] **Step 3: Implementasi.** Middleware: bila `$request->user()` adalah `User` aktif dan `Cache::add("session-activity:{$id}", true, self::THROTTLE_SECONDS)` berhasil, dispatch `UserSessionActive`. Komentarnya: `// BR-IAM-21 — setiap request adalah aktivitas sesi; throttle membuat badge basi ≤ 1 menit`. Event `ShouldBroadcast` saja; tidak ada transaksi di jalur ini.

- [ ] **Step 4: Jalankan test, pastikan lulus**, termasuk `UserSessionTest`, `DeactivationEffectTest`, dan `AuditCoverageTest` yang sudah ada. Lalu gate penuh.

- [ ] **Step 5: Commit** — `feat: aktivitas sesi didorong ke kanal identity.activity`

---

### Task 5: Badge kehadiran langsung

**Files:**
- Create: `resources/js/composables/useSessionActivity.ts`
- Modify: `resources/js/Components/Identity/AccountStatusBadges.vue` (prop baru `userId: number`), `resources/js/Components/Identity/UserTable.vue`, `resources/js/Pages/Identity/UserShowPage.vue`, `resources/js/Components/Identity/UserSessionPanel.vue`, `resources/js/types/identity.ts` (komentar `AccountStatus`)
- Test: `tests/frontend/composables/useSessionActivity.spec.ts`, `tests/frontend/components/Identity/AccountStatusBadges.spec.ts`, `tests/frontend/components/Identity/UserSessionPanel.spec.ts` (keduanya baru)

**Interfaces:**
- Consumes: `echo()`, event `.session.active` `{ userId: number, at: string }` (Task 4).
- Produces: `ONLINE_WINDOW_MS = 5 * 60_000`; `useLiveAccountStatus(userId: () => number, status: () => AccountStatus): ComputedRef<AccountStatus>`.

- [ ] **Step 1: Test Vitest yang gagal** (fake timers, `@/lib/echo` di-mock):
  - Status awal `{ online: false, lastActiveAt: null }`. Memicu `{ userId: 7, at: <sekarang> }` → status user 7 `online: true`, `lastActiveAt: at`. Status user 8 tidak berubah.
  - Waktu maju 5 menit + 30 detik → user 7 `online: false`, `lastActiveAt` tetap terbaca ([IAM-SESSION-02] di sisi klien).
  - `lastActiveAt` dari server yang lebih baru daripada event tetap dipakai (ambil yang terbaru).
  - Dua komponen pemakai → satu `private('identity.activity')`. Keduanya unmount → `leave('identity.activity')` dan interval berhenti.
  - `echo()` `null` → status tetap seperti prop dan `online` tetap padam setelah jendela lewat.
  - `AccountStatusBadges` menampilkan "Sedang aktif" setelah event untuk `userId`-nya. `UserSessionPanel` berganti dari "Belum ada sesi tercatat" ke "Sedang aktif".

- [ ] **Step 2: Jalankan, pastikan gagal.**

- [ ] **Step 3: Implementasi.**
  - State modul: `reactive(new Map<number, string>())` untuk event, `ref(Date.now())` yang berdetak setiap 30 detik selama ada pemakai, dan hitungan pemakai yang dinaikkan di `onMounted` dan diturunkan di `onUnmounted`.
  - `online` diturunkan dari `lastActiveAt` terbaru dengan `now - Date.parse(lastActiveAt) <= ONLINE_WINDOW_MS`. Beri komentar bahwa konstanta ini cermin `SessionRegistry::ONLINE_WINDOW_MINUTES` (BR-IAM-21).
  - Kedua komponen mengganti pembacaan `status.online`/`status.lastActiveAt` dengan hasil `useLiveAccountStatus`. `UserTable` mengoper `:user-id="row.id"` dan `UserShowPage` mengoper `:user-id="user.id"`.

- [ ] **Step 4: Jalankan test, pastikan lulus**, lalu `npm run quality`.

- [ ] **Step 5: Commit** — `feat(fe): badge Sedang aktif berubah tanpa reload`

---

### Task 6: Cek browser dan tutup irisan

**Files:**
- Modify: `docs/design/fe-v2-design.md` (§8 RT1 ☑ dan teks kolom backend sesuai W168; §9 W168–W170; K7 dirujuk ke W168)
- Modify: `README.md` hanya bila sudah ada bagian menjalankan aplikasi (tambah `reverb:start`)

- [ ] **Step 1: Cek di browser.** Jalankan resep browser dari memori (server `php -S` dari `public/`, `SESSION_DRIVER=database`), ditambah `php artisan reverb:start` dan `php artisan queue:work`, dengan `BROADCAST_CONNECTION=reverb` dan aset dibangun dengan `VITE_REVERB_*`. Pakai dua konteks Playwright:
  - (a) Sari membuka dashboard; admin menonaktifkan Sari → tab Sari mendarat di login dengan banner "Akun Anda dinonaktifkan…" dalam ≤ 10 detik, tanpa interaksi.
  - (b) Sari yang lain diaktifkan kembali, login, lalu admin "Akhiri Semua Sesi" → banner "Sesi Anda diakhiri…".
  - (c) Admin membuka daftar user, Budi login di konteks ketiga → badge Budi menyala tanpa reload.
  - (d) Reverb dihentikan, lalu admin menonaktifkan user → flash sukses dan tidak ada 500 (Review Focus 1).
  - Nol error konsol di semua skenario.

- [ ] **Step 2: Gate penuh** satu per satu di bawah watchdog: pint, phpstan dua tier, pest, `npm run quality`, `vite build`. Laporkan angka test apa adanya.

- [ ] **Step 3: Docs.** §8 RT1 ☑. Catat W168, W169 (termasuk syarat deploy), dan W170 di §9. Temuan minor yang ditunda masuk §10 sebagai E2E-15 dst.

- [ ] **Step 4: Commit** — `feat(fe): irisan RT1 selesai — logout seketika dan kehadiran langsung`
