Feature: Gating Child Submission
  Laporan monev dan kelengkapan berkas adalah child FormSubmission, dan hanya
  boleh lahir dari induk yang sudah disetujui.

  @BR-FE-04 @M0 @denial
  Scenario Outline: [FE-CHILD-01] Child tidak bisa dibuat dari induk yang belum disetujui
    Given submission "P-001" berstatus <status>
    When Lead Researcher membuat laporan monev untuk "P-001"
    Then pembuatan ditolak karena induk belum APPROVED

    Examples:
      | status         |
      | DRAFT          |
      | SUBMITTED      |
      | UNDER_REVIEW   |
      | NEEDS_REVISION |
      | RESUBMITTED    |
      | REJECTED       |
      | WITHDRAWN      |

  @BR-FE-04 @M0 @happy
  Scenario: [FE-CHILD-02] Child bisa dibuat dari induk yang sudah disetujui
    Given submission "P-001" berstatus APPROVED
    When Lead Researcher membuat laporan monev untuk "P-001"
    Then child FormSubmission terbentuk dengan parent_submission_id "P-001"

  @BR-FE-04 @M0 @boundary
  Scenario: [FE-CHILD-03] Child tetap ada setelah induk ditarik
    Given submission "P-001" berstatus APPROVED dengan satu laporan monev
    When Operator menarik "P-001" menjadi WITHDRAWN
    Then laporan monev tersebut tetap ada sebagai data historis
