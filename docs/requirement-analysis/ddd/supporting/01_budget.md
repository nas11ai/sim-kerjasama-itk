# BC: Budget

**Klasifikasi:** 🟡 Supporting Domain  
**Versi:** 2.0  
**Status:** Draft

---

## Responsibility

Mengelola rencana anggaran untuk sebuah Submission. Semua data FK ke `form_submission_id` — tidak ada tabel `submissions` terpisah. Anggaran dikunci setelah Submission `APPROVED`.

---

## Activity Diagram

```mermaid
flowchart TD
    START([Researcher buka tab Budget]) --> A[Pilih Budget Component]
    A --> B[Tambah Line Item<br/>item name, volume, unit, unit price]
    B --> C[Auto-calculate<br/>total = volume × unit_price]
    C --> D{Tambah item lagi?}
    D -->|Ya| A
    D -->|Tidak| E[Auto-calculate Grand Total]
    E --> F{Total > max_budget<br/>dari Scheme?}
    F -->|Ya| G[⚠️ Warning — kurangi anggaran]
    G --> B
    F -->|Tidak| H[Save — sync total<br/>ke submission display]

    subgraph LOCK["After Approval"]
        K([Event: ProposalApproved]) --> L[is_locked = true<br/>budget tidak bisa diedit]
    end
```

---

## Aggregates

```mermaid
classDiagram
    class BudgetLineItem {
        +BudgetLineItemId id
        +FormSubmissionId form_submission_id
        +BudgetComponentId component_id
        +string item_name
        +int volume
        +string unit
        +Money unit_price
        +Money total
        +calculate()
    }

    class BudgetComponent {
        +BudgetComponentId id
        +string name
        +bool is_active
    }

    class SubmissionOuter {
        +SubmissionOuterId id
        +FormSubmissionId form_submission_id
        +OuterTypeId outer_type_id
        +Money amount
        +string description
    }

    class OuterType {
        +OuterTypeId id
        +string name
    }

    BudgetLineItem "*" --> "1" BudgetComponent : categorized_by
    SubmissionOuter "*" --> "1" OuterType : typed_as
```

---

## Business Rules

| Kode      | Rule                                                                                  |
| --------- | ------------------------------------------------------------------------------------- |
| BR-BUD-01 | Total BudgetLineItems ≤ `schemes.max_budget`                                          |
| BR-BUD-02 | Budget tidak bisa diedit setelah Submission `APPROVED`                                |
| BR-BUD-03 | Volume > 0 dan unit_price > 0 untuk setiap BudgetLineItem                             |
| BR-BUD-04 | SubmissionOuter hanya bisa diubah jika period config mengizinkan (`can_update_outer`) |
| BR-BUD-05 | BudgetComponent yang `is_active = false` tidak bisa dipilih untuk item baru           |

---

## Domain Events

| Event          | Trigger                   | Consumer |
| -------------- | ------------------------- | -------- |
| `BudgetLocked` | ProposalApproved diterima | —        |
