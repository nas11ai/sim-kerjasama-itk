Feature: Judul Pengajuan
  Setiap pengajuan pada Form yang sama tadinya tampil dengan judul Form-nya, sehingga
  peneliti dan Operator harus membuka detail untuk membedakannya. Field bertipe
  submission_title menjadikan jawabannya judul pengajuan (BR-SM-22); Form tanpa field
  itu tetap tampil dengan judul Form.

  Background:
    Given Form "Pengajuan Penelitian 2026" punya field "Judul Penelitian" bertipe submission_title

  @BR-SM-22 @M1 @RESEARCHER-02-01 @happy
  Scenario: [SM-TITLE-01] Jawaban field judul menjadi judul pengajuan
    Given "Budi" punya draft pada "Pengajuan Penelitian 2026"
    When "Budi" mengisi "Judul Penelitian" dengan "Biochar Lahan Gambut" lalu menyimpan langkah 2
    Then daftar pengajuan "Budi" menampilkan judul "Biochar Lahan Gambut"
    And detail pengajuan itu berjudul "Biochar Lahan Gambut"

  @BR-SM-22 @M1 @RESEARCHER-02-01 @boundary
  Scenario: [SM-TITLE-02] Form tanpa field judul memakai judul Form
    Given Form "Hibah Mandiri" tidak punya field bertipe submission_title
    And "Budi" punya draft pada "Hibah Mandiri"
    Then daftar pengajuan "Budi" menampilkan judul "Hibah Mandiri"

  @BR-SM-22 @M1 @RESEARCHER-02-06 @boundary
  Scenario: [SM-TITLE-03] Jawaban judul yang dikosongkan kembali ke judul Form
    Given draft "Budi" berjudul "Biochar Lahan Gambut"
    When "Budi" mengosongkan "Judul Penelitian" lalu menyimpan langkah 2
    Then pengajuan itu tampil dengan judul "Pengajuan Penelitian 2026"

  @BR-SM-22 @M1 @ADMIN-08-06 @denial
  Scenario: [SM-TITLE-04] Form tidak boleh punya dua field judul pengajuan
    When Admin menyimpan Form dengan dua field bertipe submission_title
    Then penyimpanan ditolak dengan pesan "Form hanya boleh punya satu field bertipe Judul pengajuan."

  @BR-SM-22 @M1 @OPERATOR-02-01 @happy
  Scenario: [SM-TITLE-05] Pencarian menemukan pengajuan dari judulnya
    Given pengajuan "Budi" berjudul "Biochar Lahan Gambut" sudah disubmit
    When Operator mencari "biochar" di daftar pengajuan
    Then pengajuan "Budi" muncul di hasil
    When Operator mencari "biochar" di pencarian global
    Then pengajuan "Budi" muncul dengan label "Biochar Lahan Gambut"
