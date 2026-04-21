# BC: System Configuration

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.0  
**Status:** Draft

---

## Responsibility

Master data yang digunakan context lain sebagai lookup. **Tidak lagi** mencakup Faculty dan StudyProgram — keduanya sudah digantikan oleh `Organization` tree di BC Identity & Access.

---

## Activity Diagram

```mermaid
flowchart TD
    START([Admin buka System Configuration]) --> A{Pilih entity}
    A -->|SubmissionType| B[Read-only\nhanya via seeder]
    A -->|Tipe lainnya| C[CRUD + toggle is_active]
    C --> D{is_active?}
    D -->|false| E[Tidak muncul di pilihan user]
    D -->|true| F[Muncul di pilihan user]
    C --> G{Ada relasi aktif?}
    G -->|Ya| H[❌ Tidak bisa delete]
    G -->|Tidak| I[✅ Bisa delete]
```

---

## Entities

```mermaid
classDiagram
    class SubmissionType {
        +SubmissionTypeId id
        +string name
        +string slug
    }

    class JournalType {
        +JournalTypeId id
        +string name
        +bool is_active
    }

    class IntellectualPropertyType {
        +IntellectualPropertyTypeId id
        +string name
        +bool is_active
    }

    class PrototypeType {
        +PrototypeTypeId id
        +string name
        +bool is_active
    }

    class MeetingType {
        +MeetingTypeId id
        +string name
        +bool is_active
    }

    class SchemeType {
        +SchemeTypeId id
        +string name
        +string description
    }

    class TechnologyReadinessLevel {
        +TechnologyReadinessLevelId id
        +string name
        +int level
        +string description
    }
```

## Data per Consumer

| Entity | Dikonsumsi oleh |
|---|---|
| `SubmissionType` | Scheme, Submission, Review, Monev |
| `JournalType` | Research Output |
| `IntellectualPropertyType` | Research Output |
| `PrototypeType` | Research Output |
| `MeetingType` | Research Output |
| `SchemeType` | Scheme |
| `TechnologyReadinessLevel` | Scheme |

## Business Rules

| Kode | Rule |
|---|---|
| BR-SC-01 | Entity tidak bisa di-delete jika masih ada relasi aktif |
| BR-SC-02 | `SubmissionType` immutable — hanya via seeder |
| BR-SC-03 | Perubahan nama entity tidak mengubah data historis |
