# BC: Identity & Access

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.1  
**Status:** Draft

---

## Responsibility

Mengelola autentikasi, profil user, hierarki organisasi, dan kontrol akses. Dua pilar utama:

- **Spatie Permission** → _apa yang boleh dilakukan_ (authorization)
- **Organization tree** → _dari mana user berasal_ (identity/scope)
  Keduanya independen. `FormAccessControl` menggunakan **permission + org** — bukan role + org — sehingga user dengan custom permission langsung (tanpa role) tetap mendapat akses yang sesuai.

---

## Activity Diagram

### Jalur 1 — Self-Register (Dosen ITK)

```mermaid
flowchart TD
    START([User buka /register]) --> A[Isi email @itk.ac.id<br/>+ password + NIDN]
    A --> B{Domain email<br/>valid ITK?}
    B -->|Tidak| ERR[❌ Tolak]
    B -->|Ya| C[Pilih Organisasi<br/>via OrgTreePicker]
    C --> D[Submit Register]
    D --> E[Role 'researcher'<br/>auto-assigned<br/>→ permission set researcher]
    E --> F[Status: pending<br/>belum bisa submit proposal]
    F --> G[Operator terima<br/>notifikasi verifikasi NIDN]
    G --> H{NIDN valid?}
    H -->|Tidak| I[Reject + notifikasi user]
    H -->|Ya| J[Status: active]
    J --> END([User bisa login & submit])
```

### Jalur 2 — Invitation Link (External)

```mermaid
flowchart TD
    START([Operator buat InvitationToken]) --> A[Set organization_id<br/>permission set, expires_at, max_uses]
    A --> B[Generate token URL<br/>/register?token=xxx]
    B --> C[Operator share link<br/>ke PIC institusi mitra]
    C --> D[User eksternal buka link]
    D --> E{Token valid<br/>& belum expired?}
    E -->|Tidak| ERR[❌ Link tidak valid]
    E -->|Ya| F[Form register<br/>org & permission pre-filled]
    F --> G[User isi nama, email, password]
    G --> H[Register → status: active<br/>langsung tanpa verifikasi manual]
    H --> I[used_count++]
```

### Jalur 3 — Reviewer (Ditunjuk Operator)

```mermaid
flowchart TD
    START([Operator buka Reviewer Management]) --> A[Cari user by NIDN<br/>atau nama]
    A --> B[Pilih user yang sudah ada]
    B --> C{Tipe reviewer?}
    C -->|Internal| D[Assign Spatie role<br/>'reviewer_internal']
    C -->|Eksternal| E[Assign Spatie role<br/>'reviewer_external']
    D --> F[Buat record di tabel reviewers<br/>reviewer_type = internal]
    E --> G[Buat record di tabel reviewers<br/>reviewer_type = external]
    F --> END([User punya dua role:<br/>researcher + reviewer_internal])
    G --> END
```

### Custom Permission untuk User Spesifik

```mermaid
flowchart TD
    START([Admin buka User Management]) --> A[Pilih user]
    A --> B[Tambah direct permission<br/>e.g. reviewers.evaluate]
    B --> C[Spatie assign permission<br/>langsung ke user<br/>tanpa tambah role baru]
    C --> D[User sekarang punya<br/>permission tersebut<br/>di samping permission dari role-nya]
    D --> E[Access check di FormAccessControl<br/>getAllPermissions cover keduanya]
```

---

## Aggregates

```mermaid
classDiagram
    class Organization {
        +OrganizationId id
        +string name
        +string type
        +OrganizationId parent_id
        +bool is_active
        +Json metadata
        +subtreeIds() OrganizationId[]
        +ancestors() Organization[]
    }

    class User {
        +UserId id
        +string email
        +string password
        +bool is_active
        +DateTime email_verified_at
        +can(permission) bool
        +getAllPermissions() Collection
        +deactivate()
    }

    class UserProfile {
        +UserProfileId id
        +UserId user_id
        +OrganizationId organization_id
        +string nidn
        +string full_name
        +string phone
        +string expertise
        +string photo_path
        +VerificationStatus status
        +isComplete() bool
        +isActive() bool
    }

    class InvitationToken {
        +InvitationTokenId id
        +string token
        +OrganizationId organization_id
        +string[] permissions
        +int max_uses
        +int used_count
        +DateTime expires_at
        +UserId created_by
        +isValid() bool
        +consume()
    }

    class Role {
        +RoleId id
        +string name
        +Permission[] permissions
    }

    class Permission {
        +PermissionId id
        +string name
    }

    Organization "0..1" --> "0..*" Organization : parent_of
    User "1" --> "1" UserProfile : has
    UserProfile "*" --> "1" Organization : belongs_to
    User "many" --> "many" Role : assigned
    User "many" --> "many" Permission : direct_permissions
    Role "many" --> "many" Permission : has
```

---

## Spatie Permission Design

**Permissions** (granular, yang di-check di kode dan di `FormAccessControl`):

```
submissions.create          submissions.view-own
submissions.view-all        budget.edit
members.manage              reviewers.assign
reviewers.evaluate          reviewers.view-scores-others
periods.manage              schemes.manage
outputs.manage              users.verify
users.manage
```

**Roles** (bundle permissions — mayoritas user):

| Role                | Permissions                                                                     |
| ------------------- | ------------------------------------------------------------------------------- |
| `researcher`        | submissions.create, view-own, budget.edit, members.manage, outputs.manage       |
| `reviewer_internal` | reviewers.evaluate, submissions.view-assigned, **reviewers.view-scores-others** |
| `reviewer_external` | reviewers.evaluate, submissions.view-assigned                                   |
| `operator`          | submissions.view-all, reviewers.assign, periods.manage, users.verify            |
| `admin`             | semua                                                                           |

**Custom permission (direct assign ke user):**

Untuk edge case — satu user tertentu butuh akses yang tidak cocok dengan role manapun. Admin assign permission langsung ke user via Spatie tanpa membuat role baru.

```php
// Assign permission langsung ke user
$user->givePermissionTo('reviewers.evaluate');

// Access check — transparan, cover role maupun direct
$user->can('reviewers.evaluate');           // true
$user->getAllPermissions()->pluck('name'); // include semua sumber
```

---

## Bagaimana Permission + Org Bekerja di FormAccessControl

`FormAccessControl` menyimpan `permission` (string) + `organization_id`. Access check:

```php
function canAccessForm(User $user, Form $form): bool
{
    $userPermissions = $user->getAllPermissions()->pluck('name');
    $userOrgSubtree  = Organization::subtreeIds(
        $user->profile->organization_id
    );

    return FormAccessControl::where('form_id', $form->id)
        ->whereIn('permission', $userPermissions)
        ->whereIn('organization_id', $userOrgSubtree)
        ->exists();
}
```

Contoh konfigurasi FormAccessControl:

| Form                  | Permission                     | Organization   | Efek                                         |
| --------------------- | ------------------------------ | -------------- | -------------------------------------------- |
| Form Pengajuan        | `submissions.create`           | ITK (root)     | Semua researcher ITK bisa akses              |
| Form Pengajuan        | `submissions.create`           | Unmul (root)   | Semua researcher Unmul bisa akses            |
| Form Evaluasi         | `reviewers.evaluate`           | ITK (root)     | Reviewer internal & eksternal ITK bisa akses |
| Form Laporan Internal | `reviewers.view-scores-others` | Fakultas Sains | Hanya reviewer_internal dari Fakultas Sains  |

---

## Business Rules

| Kode      | Rule                                                                                                                               |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| BR-IAM-01 | Email domain `@itk.ac.id` wajib untuk jalur self-register internal                                                                 |
| BR-IAM-02 | NIDN harus unik di seluruh sistem                                                                                                  |
| BR-IAM-03 | UserProfile wajib lengkap sebelum user bisa membuat Submission                                                                     |
| BR-IAM-04 | User berstatus `pending` bisa login tapi tidak bisa submit proposal                                                                |
| BR-IAM-05 | Deaktivasi user tidak delete data — cukup `is_active = false`                                                                      |
| BR-IAM-06 | InvitationToken invalid jika `used_count >= max_uses` atau `expires_at` sudah lewat                                                |
| BR-IAM-07 | User dengan role `reviewer_internal` atau `reviewer_external` tidak bisa me-review submission yang ia menjadi `ResearchMember`-nya |
| BR-IAM-08 | Organization tidak bisa di-delete jika masih ada UserProfile di subtree-nya                                                        |
| BR-IAM-09 | Direct permission ke user tidak menghapus permission dari role — keduanya additive                                                 |

---

## Domain Events

| Event               | Trigger                  | Consumer     |
| ------------------- | ------------------------ | ------------ |
| `UserRegistered`    | User berhasil register   | Notification |
| `UserVerified`      | Operator verifikasi NIDN | Notification |
| `UserDeactivated`   | Admin nonaktifkan user   | Notification |
| `ReviewerAppointed` | Operator assign reviewer | Notification |

---

## Database Notes (PostgreSQL)

Recursive CTE untuk org subtree traversal:

```sql
WITH RECURSIVE org_subtree AS (
    SELECT id FROM organizations WHERE id = $1
    UNION ALL
    SELECT o.id FROM organizations o
    INNER JOIN org_subtree ot ON o.parent_id = ot.id
    WHERE o.is_active = true
)
SELECT id FROM org_subtree;
```

Untuk performa, bisa di-cache di Redis dengan TTL pendek (misalnya 5 menit) karena org tree jarang berubah.
