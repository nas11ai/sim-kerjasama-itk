Feature: State Machine Pengajuan
  Status hanya berpindah lewat transitionTo(); tidak ada lompatan. Status terminal
  hanya bisa disentuh Operator lewat override.

  @BR-SM-03 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-STATE-01] Draft berpindah ke submitted lewat state machine
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" submit "P-001"
    Then status "P-001" menjadi SUBMITTED

  @BR-SM-03 @M1 @denial
  Scenario Outline: [SM-STATE-02] Lompatan status terlarang ditolak
    Given submission "P-001" berstatus <dari>
    When sistem mencoba memindahkan "P-001" ke <ke>
    Then perpindahan ditolak

    Examples:
      | dari      | ke          |
      | DRAFT     | APPROVED    |
      | DRAFT     | RESUBMITTED |
      | APPROVED  | DRAFT       |
      | REJECTED  | APPROVED    |
      | REJECTED  | RESUBMITTED |
      | WITHDRAWN | APPROVED    |

  @BR-SM-03 @M1 @RESEARCHER-02-05 @happy
  Scenario: [SM-STATE-03] Revisi kembali masuk antrean review
    Given submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    When "Budi" resubmit "P-001"
    Then status "P-001" menjadi RESUBMITTED
    And status "P-001" menjadi UNDER_REVIEW

  @BR-SM-05 @M1 @denial
  Scenario Outline: [SM-STATE-04] Isi pengajuan terkunci pada status terminal
    Given submission "P-001" milik "Budi" berstatus <status>
    When "Budi" menyunting isi "P-001"
    Then penyuntingan ditolak dengan 403

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |

  @BR-SM-05 @M1 @OPERATOR-02-02 @happy
  Scenario: [SM-STATE-05] Operator override tetap bisa menyentuh status terminal
    Given submission "P-001" berstatus APPROVED
    When Operator mengubah "P-001" lewat override
    Then perubahan tersimpan
    And perubahan tercatat di jejak audit
