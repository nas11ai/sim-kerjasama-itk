"""Membangun docs/qa/matriks-uji-qa.xlsx dari scripts/qa/kasus_uji.py.

Jalankan dari akar repo:
    uv run --with openpyxl python scripts/qa/buat_matriks_uji.py

Berkas harus tetap bisa diimpor ke Google Sheets: hanya data validation daftar, conditional formatting
berbasis nilai sel, COUNTIF/COUNTIFS/COUNTA, freeze panes, dan wrap text. Desain:
docs/design/2026-11-14-matriks-uji-qa-design.md.
"""

import re
import sys
from pathlib import Path

from openpyxl import Workbook
from openpyxl.formatting.rule import CellIsRule, FormulaRule
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parent))
import kasus_uji as K  # noqa: E402

ROOT = Path(__file__).resolve().parents[2]
BDD = ROOT / "docs/requirement-analysis/bdd"
OUT = ROOT / "docs/qa/matriks-uji-qa.xlsx"

FONT = "Arial"
MAX_ROWS = 1000  # baris data yang disiapkan validasi dan rumus

HASIL = ["Belum diuji", "Lulus", "Gagal", "Terblokir"]
PENGUJI = ["QA1", "QA2", "QA3", "QA4"]
TINGKAT = ["Kritis", "Tinggi", "Sedang", "Rendah"]
STATUS_TEMUAN = ["Baru", "Sedang diperbaiki", "Sudah diperbaiki", "Lulus uji ulang", "Bukan bug"]

HEADER_FILL = PatternFill("solid", fgColor="1F3A5F")
HEADER_FONT = Font(name=FONT, bold=True, color="FFFFFF")
ISIAN_FILL = PatternFill("solid", fgColor="FFF4CC")  # sel yang diisi penguji
ALUR_FILL = PatternFill("solid", fgColor="E8EEF5")
BORDER = Border(*(Side(style="thin", color="C8CED6"),) * 4)
WRAP = Alignment(wrap_text=True, vertical="top")
BODY = Font(name=FONT, size=10)
BOLD = Font(name=FONT, size=10, bold=True)


# ------------------------------------------------------------------ data


def bdd_scenarios() -> list[dict]:
    """Semua skenario dari berkas .feature, urut seperti di folder."""
    rows = []
    for path in sorted(BDD.rglob("*.feature")):
        tags = ""
        for line in path.read_text(encoding="utf-8").splitlines():
            stripped = line.strip()
            if stripped.startswith("@"):
                tags = stripped
                continue
            match = re.match(r"Scenario(?: Outline)?:\s*\[([A-Z0-9-]+)\]\s*(.*)", stripped)
            if not match:
                continue
            kelas = "Berhasil" if "@happy" in tags else "Ditolak" if "@denial" in tags else "Batas aturan"
            rows.append(
                # Matriks dibaca penguji: tanpa tanda pisah panjang, seperti teks di layar.
                dict(id=match.group(1), judul=match.group(2).replace(" — ", ": ").replace("—", "-"),
                     kelas=kelas,
                     berkas=str(path.relative_to(BDD)).replace("\\", "/"))
            )
    return rows


def pending_ids() -> set[str]:
    text = (BDD / "pending.txt").read_text(encoding="utf-8")
    return {line.strip() for line in text.splitlines() if line.strip() and not line.startswith("#")}


def prepare() -> tuple[list[dict], list[dict]]:
    scenarios = bdd_scenarios()
    known = {s["id"] for s in scenarios}
    alur_names = dict(K.ALUR)
    penguji_by_alur = {alur: qa for qa, alurs in K.PEMBAGIAN.items() for alur in alurs}

    errors = []
    if set(alur_names) != set(penguji_by_alur):
        errors.append(f"PEMBAGIAN tidak mencakup tepat semua alur: {set(alur_names) ^ set(penguji_by_alur)}")

    counters: dict[str, int] = {}
    cases = []
    for case in K.KASUS:
        alur = case["alur"]
        counters[alur] = counters.get(alur, 0) + 1
        case = dict(case, id=f"{alur}-{counters[alur]:02d}", nama_alur=alur_names[alur],
                    penguji=penguji_by_alur[alur])
        refs = [r.strip() for r in case["bdd"].split(",") if r.strip()]
        for ref in refs:
            if ref not in known:
                errors.append(f"{case['id']}: ID BDD tidak dikenal {ref}")
        case["refs"] = refs
        for key, value in case.items():
            if isinstance(value, str) and "—" in value:
                errors.append(f"{case['id']}.{key}: memakai tanda pisah panjang")
        cases.append(case)

    if errors:
        sys.exit("Matriks tidak dibangun:\n- " + "\n- ".join(errors))
    return cases, scenarios


# ------------------------------------------------------------------ gaya


def header(ws, row: int, labels: list[str], widths: list[int] | None = None) -> None:
    for col, label in enumerate(labels, start=1):
        cell = ws.cell(row=row, column=col, value=label)
        cell.font = HEADER_FONT
        cell.fill = HEADER_FILL
        cell.alignment = Alignment(wrap_text=True, vertical="center")
        cell.border = BORDER
    if widths:
        for col, width in enumerate(widths, start=1):
            ws.column_dimensions[get_column_letter(col)].width = width


def body(ws, row: int, values: list, bold_cols: tuple[int, ...] = ()) -> None:
    for col, value in enumerate(values, start=1):
        cell = ws.cell(row=row, column=col, value=value)
        cell.font = BOLD if col in bold_cols else BODY
        cell.alignment = WRAP
        cell.border = BORDER


def title(ws, text: str, sub: str | None = None) -> int:
    ws["A1"] = text
    ws["A1"].font = Font(name=FONT, size=14, bold=True, color="1F3A5F")
    if sub:
        ws["A2"] = sub
        ws["A2"].font = Font(name=FONT, size=10, italic=True)
        return 4
    return 3


def dropdown(ws, values: list[str], ref: str, prompt: str) -> None:
    dv = DataValidation(type="list", formula1='"' + ",".join(values) + '"', allow_blank=True)
    dv.error = "Pilih salah satu nilai dari daftar."
    dv.errorTitle = "Nilai tidak dikenal"
    dv.prompt = prompt
    dv.showErrorMessage = True
    ws.add_data_validation(dv)
    dv.add(ref)


def hasil_colors(ws, ref: str) -> None:
    for value, color in (("Lulus", "D4EDDA"), ("Gagal", "F8D7DA"), ("Terblokir", "FFE5B4")):
        ws.conditional_formatting.add(
            ref, CellIsRule(operator="equal", formula=[f'"{value}"'], fill=PatternFill("solid", fgColor=color)))


# ------------------------------------------------------------------ lembar

KOLOM = [
    ("No", 5), ("ID Kasus", 9), ("Alur", 6), ("Nama Alur", 18), ("Skenario", 30), ("Prioritas", 9),
    ("Masuk sebagai", 26), ("Lokasi halaman", 34), ("Diketahui (kondisi awal)", 36),
    ("Ketika (yang dilakukan)", 46), ("Maka (yang harus terlihat)", 46), ("Mengubah data?", 9),
    ("Rujukan aturan (BDD)", 20), ("Penguji", 8), ("Tanggal uji", 11), ("Hasil", 11),
    ("Catatan / ID Temuan", 30),
]
COL = {name: i + 1 for i, (name, _) in enumerate(KOLOM)}
L = {name: get_column_letter(i) for name, i in COL.items()}
ISIAN = ("Penguji", "Tanggal uji", "Hasil", "Catatan / ID Temuan")


def sheet_kasus(wb: Workbook, cases: list[dict]) -> None:
    ws = wb.create_sheet("Kasus Uji")
    header(ws, 1, [k for k, _ in KOLOM], [w for _, w in KOLOM])
    ws.row_dimensions[1].height = 32
    for i, case in enumerate(cases, start=2):
        body(ws, i, [
            i - 1, case["id"], case["alur"], case["nama_alur"], case["skenario"], case["prioritas"],
            case["akun"], case["lokasi"], case["diketahui"], case["ketika"], case["maka"], case["ubah"],
            ", ".join(case["refs"]), case["penguji"], None, "Belum diuji", None,
        ], bold_cols=(COL["Skenario"],))
        for name in ISIAN:
            ws.cell(row=i, column=COL[name]).fill = ISIAN_FILL
        ws.cell(row=i, column=COL["Tanggal uji"]).number_format = "dd/mm/yyyy"
    last = MAX_ROWS
    dropdown(ws, HASIL, f"{L['Hasil']}2:{L['Hasil']}{last}", "Hasil uji kasus ini")
    dropdown(ws, PENGUJI, f"{L['Penguji']}2:{L['Penguji']}{last}", "Siapa yang menguji")
    dropdown(ws, ["Tinggi", "Sedang", "Rendah"], f"{L['Prioritas']}2:{L['Prioritas']}{last}", "Prioritas")
    dv_date = DataValidation(type="date", operator="greaterThan", formula1="DATE(2026,1,1)", allow_blank=True)
    dv_date.error = "Isi tanggal, misalnya 20/11/2026."
    dv_date.showErrorMessage = True
    ws.add_data_validation(dv_date)
    dv_date.add(f"{L['Tanggal uji']}2:{L['Tanggal uji']}{last}")
    hasil_colors(ws, f"{L['Hasil']}2:{L['Hasil']}{last}")
    ws.conditional_formatting.add(
        f"{L['Prioritas']}2:{L['Prioritas']}{last}",
        CellIsRule(operator="equal", formula=['"Tinggi"'], font=Font(name=FONT, bold=True, color="9C0006")))
    ws.freeze_panes = ws.cell(row=2, column=COL["Skenario"] + 1)
    ws.auto_filter.ref = f"A1:{get_column_letter(len(KOLOM))}{len(cases) + 1}"


def sheet_petunjuk(wb: Workbook, cases: list[dict]) -> None:
    ws = wb.active
    ws.title = "Petunjuk"
    ws.column_dimensions["A"].width = 26
    ws.column_dimensions["B"].width = 100
    row = title(ws, "Matriks Uji QA SIMPAS",
                f"{len(cases)} kasus uji dalam {len(K.ALUR)} alur. Data uji: docs/qa/data-uji.md. Staging: docs/qa/staging.md.")
    rows = [
        ("Memasukkan ke Google Sheets", None),
        ("Cara memasukkan", "Google Drive › Baru › Upload file › pilih matriks-uji-qa.xlsx › klik kanan › Buka dengan › Google Spreadsheet, lalu File › Simpan sebagai Google Spreadsheet. Bagikan ke semua penguji dengan akses Editor."),
        ("Yang ikut terbawa", "Dropdown, warna Hasil, saringan, baris judul yang dibekukan, dan rumus di lembar Ringkasan. Satu file dipakai bersama; setiap penguji mengisi baris miliknya saja."),
        ("Cara pakai", None),
        ("1. Lihat bagian Anda", "Buka lembar \"Pembagian\". Setiap penguji memegang beberapa alur dan memakai SET datanya sendiri (QA1 = set 1, dan seterusnya)."),
        ("2. Saring kasus Anda", "Di lembar \"Kasus Uji\", pakai panah saring di kolom \"Penguji\" dan pilih kode Anda."),
        ("3. Kerjakan berurutan", "Kerjakan satu alur dari atas ke bawah. Banyak kasus memakai hasil kasus sebelumnya (mis. pengajuan dikirim dulu, baru dinilai)."),
        ("4. Isi hasil", "Isi kolom kuning: Penguji, Tanggal uji, Hasil, Catatan / ID Temuan. Kolom lain jangan diubah."),
        ("5. Catat temuan", "Bila hasilnya Gagal, tulis satu baris di lembar \"Temuan\" dan salin ID Temuan ke kolom Catatan di Kasus Uji. Nomori dengan awalan set Anda supaya tidak bentrok: QA1 memakai T1-001, T1-002, ...; QA2 memakai T2-001, dan seterusnya."),
        ("Cara membaca kasus", None),
        ("Diketahui", "Keadaan awal sebelum Anda mulai: akun, data, atau kasus sebelumnya yang harus sudah selesai."),
        ("Ketika", "Hal yang Anda lakukan di layar. Tulisan dalam tanda kutip adalah nama tombol atau menu persis seperti di layar."),
        ("Maka", "Hal yang HARUS terlihat. Bila ada satu saja yang berbeda, hasilnya Gagal."),
        ("Lokasi halaman", "Baris pertama: jalan dari menu kiri. Baris kedua: alamat halaman (tambahkan di belakang alamat staging). Tanda … berarti angka yang berbeda tiap data."),
        ("{k}", "Ganti dengan nomor set Anda. Contoh untuk QA2: qa{k}-peneliti01@simpas.test menjadi qa2-peneliti01@simpas.test."),
        ("#3", "Pengajuan nomor 3 di set Anda (judulnya berawalan [QA{k}]). Daftar lengkapnya ada di lembar \"Akun & Data\"."),
        ("Kata sandi", "Kata sandi setiap akun uji sama dengan alamat emailnya."),
        ("Mengubah data?", "\"Ya\" berarti kasus ini mengubah data set Anda dan tidak bisa diulang tanpa reset data."),
        ("Rujukan aturan (BDD)", "Kode skenario aturan bisnis yang diuji. Tidak perlu dibaca untuk menjalankan kasus; berguna untuk pengembang."),
        ("Isi kolom Hasil", None),
        ("Lulus", "Semua yang ditulis di \"Maka\" terlihat."),
        ("Gagal", "Ada yang berbeda dari \"Maka\". Catat di lembar Temuan."),
        ("Terblokir", "Kasus tidak bisa dijalankan (mis. kasus sebelumnya gagal, staging mati, atau tidak menemukan tombolnya). Tulis alasannya di Catatan."),
        ("Belum diuji", "Nilai awal."),
        ("Aturan main bersama", None),
        ("Hanya data set Anda", "Operator dan Admin melihat pengajuan semua set. Kerjakan hanya yang berawalan [QA{k}] milik set Anda."),
        ("Konfigurasi bersama", "Formulir, Tahap, Periode, Skema, Organisasi, Pengumuman, dan Konfigurasi Umum dipakai semua penguji. Jangan menyunting data bawaan; buat data baru berawalan QA{k}."),
        ("Akun Admin dipakai bersama", "qa-admin@simpas.test dipakai semua penguji. Jangan mengubah kata sandinya."),
        ("Email", "Staging tidak mengirim email sungguhan. Untuk kasus yang butuh tautan email, minta pemilik VM membacakannya dari log."),
        ("Reset data", "Reset (scripts/staging/reset-data.sh) menghapus hasil SEMUA penguji. Lakukan hanya bila semua sepakat."),
        ("Contoh baris yang sudah diisi", None),
        ("Penguji", "QA2"),
        ("Tanggal uji", "20/11/2026"),
        ("Hasil", "Gagal"),
        ("Catatan / ID Temuan", "T2-004: tombol Setujui tetap aktif selama putaran berjalan"),
        ("Contoh baris Temuan", None),
        ("ID Temuan", "T2-004"),
        ("ID Kasus", "A08-01"),
        ("Ringkasan", "Tombol Setujui aktif selama putaran penilaian berjalan"),
        ("Langkah mengulang", "1. Masuk sebagai qa2-operator@simpas.test\n2. Buka Semua Pengajuan › #4\n3. Lihat Aksi Operator"),
        ("Yang terjadi", "Tombol Setujui bisa ditekan"),
        ("Yang seharusnya", "Tombol Setujui belum bisa dipakai, dengan keterangan alasannya"),
        ("Tingkat", "Tinggi (Kritis: data rusak/bocor atau alur utama macet; Tinggi: fitur salah; Sedang: mengganggu tapi ada jalan lain; Rendah: tampilan atau teks)"),
        ("Tautan tangkapan layar", "Tautan Google Drive atau gambar yang ditempel di sel"),
    ]
    for label, text in rows:
        if text is None:
            ws.cell(row=row, column=1, value=label).font = Font(name=FONT, size=11, bold=True, color="1F3A5F")
            row += 1
            continue
        body(ws, row, [label, text], bold_cols=(1,))
        row += 1
    ws.freeze_panes = "A4"


def sheet_temuan(wb: Workbook, case_count: int) -> None:
    ws = wb.create_sheet("Temuan")
    cols = [("ID Temuan", 10), ("Tanggal", 11), ("Penguji", 8), ("ID Kasus", 10), ("Ringkasan", 34),
            ("Langkah mengulang", 44), ("Yang terjadi", 36), ("Yang seharusnya", 36), ("Tingkat", 9),
            ("Tautan tangkapan layar", 26), ("Status", 14), ("Catatan pengembang", 30)]
    header(ws, 1, [c for c, _ in cols], [w for _, w in cols])
    for r in range(2, 302):
        for c in range(1, len(cols) + 1):
            ws.cell(row=r, column=c).alignment = WRAP
            ws.cell(row=r, column=c).border = BORDER
        ws.cell(row=r, column=2).number_format = "dd/mm/yyyy"
    dropdown(ws, PENGUJI, "C2:C1000", "Siapa yang menemukan")
    dropdown(ws, TINGKAT, "I2:I1000",
             "Kritis: data rusak/bocor atau alur utama macet. Tinggi: fitur salah. Sedang: mengganggu tapi ada jalan lain. Rendah: tampilan/teks.")
    dropdown(ws, STATUS_TEMUAN, "K2:K1000", "Status temuan")
    dv = DataValidation(type="list", formula1=f"='Kasus Uji'!$B$2:$B${case_count + 1}", allow_blank=True)
    dv.error = "Pilih ID kasus dari lembar Kasus Uji."
    dv.showErrorMessage = True
    ws.add_data_validation(dv)
    dv.add("D2:D1000")
    for value, color in (("Kritis", "F8D7DA"), ("Tinggi", "FFE5B4")):
        ws.conditional_formatting.add(
            "I2:I1000", CellIsRule(operator="equal", formula=[f'"{value}"'], fill=PatternFill("solid", fgColor=color)))
    ws.freeze_panes = "B2"
    ws.auto_filter.ref = "A1:L1"


def sheet_ringkasan(wb: Workbook) -> None:
    ws = wb.create_sheet("Ringkasan")
    row = title(ws, "Ringkasan Hasil Uji", "Angka dihitung otomatis dari lembar Kasus Uji dan Temuan.")
    widths = [12, 38, 10, 10, 10, 10, 12, 12]
    for col, width in enumerate(widths, start=1):
        ws.column_dimensions[get_column_letter(col)].width = width
    kasus = "'Kasus Uji'!"
    rng = lambda name: f"{kasus}${L[name]}$2:${L[name]}${MAX_ROWS}"  # noqa: E731
    labels = ["Kode", "Nama", "Jumlah kasus", "Lulus", "Gagal", "Terblokir", "Belum diuji", "% selesai"]

    def block(start: int, heading: str, keys: list[tuple[str, str]], key_col: str) -> int:
        ws.cell(row=start, column=1, value=heading).font = Font(name=FONT, size=11, bold=True, color="1F3A5F")
        header(ws, start + 1, labels)
        first = start + 2
        for i, (code, name) in enumerate(keys):
            r = first + i
            body(ws, r, [code, name], bold_cols=(1,))
            ws.cell(row=r, column=3, value=f"=COUNTIF({rng(key_col)},$A{r})")
            for col, value in ((4, "Lulus"), (5, "Gagal"), (6, "Terblokir")):
                ws.cell(row=r, column=col, value=f'=COUNTIFS({rng(key_col)},$A{r},{rng("Hasil")},"{value}")')
            ws.cell(row=r, column=7, value=f"=C{r}-D{r}-E{r}-F{r}")
            ws.cell(row=r, column=8, value=f'=IF(C{r}=0,0,(D{r}+E{r}+F{r})/C{r})')
        last = first + len(keys) - 1
        total = last + 1
        body(ws, total, ["Total", None], bold_cols=(1,))
        for col in range(3, 8):
            letter = get_column_letter(col)
            ws.cell(row=total, column=col, value=f"=SUM({letter}{first}:{letter}{last})")
        ws.cell(row=total, column=8, value=f"=IF(C{total}=0,0,(D{total}+E{total}+F{total})/C{total})")
        for r in range(first, total + 1):
            for col in range(3, 9):
                cell = ws.cell(row=r, column=col)
                cell.font = BOLD if r == total else BODY
                cell.border = BORDER
            ws.cell(row=r, column=8).number_format = "0%"
        return total + 2

    row = block(row, "Per penguji", [(qa, ", ".join(alurs)) for qa, alurs in K.PEMBAGIAN.items()], "Penguji")
    row = block(row, "Per alur", list(K.ALUR), "Alur")

    ws.cell(row=row, column=1, value="Temuan").font = Font(name=FONT, size=11, bold=True, color="1F3A5F")
    header(ws, row + 1, ["Tingkat", "Keterangan", "Semua", "Belum selesai"])
    keterangan = {
        "Kritis": "Data rusak atau bocor, atau alur utama macet",
        "Tinggi": "Fitur memberi hasil salah",
        "Sedang": "Mengganggu tetapi ada jalan lain",
        "Rendah": "Tampilan atau teks",
    }
    for i, level in enumerate(TINGKAT):
        r = row + 2 + i
        body(ws, r, [level, keterangan[level]], bold_cols=(1,))
        ws.cell(row=r, column=3, value=f"=COUNTIF(Temuan!$I$2:$I${MAX_ROWS},$A{r})")
        ws.cell(row=r, column=4, value=(
            f'=COUNTIFS(Temuan!$I$2:$I${MAX_ROWS},$A{r},Temuan!$K$2:$K${MAX_ROWS},"<>Lulus uji ulang",'
            f'Temuan!$K$2:$K${MAX_ROWS},"<>Bukan bug")'))
        for col in (3, 4):
            ws.cell(row=r, column=col).font = BODY
            ws.cell(row=r, column=col).border = BORDER
    ws.freeze_panes = "A4"


def sheet_pembagian(wb: Workbook, cases: list[dict]) -> None:
    ws = wb.create_sheet("Pembagian")
    row = title(ws, "Pembagian Alur per Penguji",
                "Satu alur dikerjakan satu orang dari atas ke bawah, di set datanya sendiri.")
    header(ws, row, ["Alur", "Nama alur", "Jumlah kasus", "Penguji (4 orang)", "Penguji (3 orang)"],
           [8, 44, 12, 16, 16])
    counts: dict[str, int] = {}
    for case in cases:
        counts[case["alur"]] = counts.get(case["alur"], 0) + 1
    penguji_by_alur = {alur: qa for qa, alurs in K.PEMBAGIAN.items() for alur in alurs}
    for code, name in K.ALUR:
        row += 1
        body(ws, row, [code, name, counts.get(code, 0), penguji_by_alur[code],
                       K.PEMBAGIAN_TIGA.get(code, penguji_by_alur[code])], bold_cols=(1,))
    row += 2
    notes = [
        "Bila hanya 3 penguji: ubah kolom Penguji di lembar Kasus Uji sesuai kolom \"Penguji (3 orang)\" (saring per alur, lalu isi ulang). Set 4 tidak dipakai.",
        "Alur boleh ditukar antarpenguji, asalkan satu alur tetap dikerjakan satu orang.",
        "Alur yang memakai dua akun sekaligus (mis. A15 dan A16) lebih mudah dengan dua browser, atau satu browser biasa ditambah satu jendela samaran (incognito).",
    ]
    for note in notes:
        ws.cell(row=row, column=1, value="Catatan").font = BOLD
        cell = ws.cell(row=row, column=2, value=note)
        cell.font = BODY
        cell.alignment = WRAP
        row += 1


def sheet_akun(wb: Workbook) -> None:
    ws = wb.create_sheet("Akun & Data")
    row = title(ws, "Akun dan Data Uji",
                "Ringkasan docs/qa/data-uji.md. Ganti {k} dengan nomor set Anda. Kata sandi = alamat email.")
    for col, width in zip("ABC", (34, 34, 60)):
        ws.column_dimensions[col].width = width
    ws.cell(row=row, column=1, value="Akun").font = Font(name=FONT, size=11, bold=True, color="1F3A5F")
    header(ws, row + 1, ["Akun", "Peran", "Keterangan"])
    akun = [
        ("qa-admin@simpas.test", "Admin", "Satu akun dipakai semua penguji"),
        ("qa{k}-operator@simpas.test", "Operator LPPM", "Melihat pengajuan semua set; kerjakan hanya set Anda"),
        ("qa{k}-penilai1@simpas.test", "Penilai internal", ""),
        ("qa{k}-penilai2@simpas.test", "Penilai internal", ""),
        ("qa{k}-penilai-luar@simpas.test", "Penilai eksternal", "Juga penilai laporan Monev"),
        ("qa{k}-peneliti01@ ... qa{k}-peneliti12@", "Peneliti", "Nomor peneliti = nomor pengajuan di tabel bawah"),
        ("qa{k}-anggota@simpas.test", "Peneliti", "Anggota biasa (bukan ketua) di pengajuan #9 sampai #12"),
        ("qa{k}-peneliti-baru@simpas.test", "Peneliti", "NIDN menunggu verifikasi"),
        ("qa{k}-peneliti-ditolak@simpas.test", "Peneliti", "NIDN ditolak"),
        ("latar-...@simpas.test", "Data latar", "Hanya untuk mengisi statistik; jangan diubah"),
    ]
    r = row + 2
    for values in akun:
        body(ws, r, list(values), bold_cols=(1,))
        r += 1
    r += 1
    ws.cell(row=r, column=1, value="Pengajuan tahun ini per set (judul berawalan [QA{k}])").font = Font(
        name=FONT, size=11, bold=True, color="1F3A5F")
    header(ws, r + 1, ["No dan pemilik", "Keadaan awal", "Judul"])
    pengajuan = [
        ("#1 peneliti01", "Draf, baru sampai langkah 2", "Pemetaan Potensi Energi Surya di Kawasan Pesisir Balikpapan"),
        ("#2 peneliti02", "Draf lengkap, siap dikirim", "Pelatihan Pengolahan Sampah Plastik untuk UMKM Kelurahan Manggar"),
        ("#3 peneliti03", "Menunggu Penilaian, belum ada penilai", "Optimasi Campuran Beton Geopolimer Berbasis Abu Terbang Batu Bara"),
        ("#4 peneliti04", "Menunggu Penilaian, baru 1 dari 2 penilai (Penilai 1)", "Pendampingan Literasi Digital bagi Guru Sekolah Dasar di Penajam"),
        ("#5 peneliti05", "Sedang Dinilai: Penilai 1 sudah mengirim lembar (belum Selesai menilai), Penilai 2 belum", "Model Prediksi Banjir Rob Berbasis Pembelajaran Mesin"),
        ("#6 peneliti06", "Sedang Dinilai, kedua penilai Selesai: siap diputuskan", "Penerapan Hidroponik Sederhana untuk Ketahanan Pangan Keluarga"),
        ("#7 peneliti07", "Perlu Revisi (Penilai 1 membuka catatan)", "Karakterisasi Biochar dari Limbah Cangkang Sawit"),
        ("#8 peneliti08", "Sedang Dinilai, putaran pemeriksaan ulang (catatan Penilai 1 masih terbuka)", "Edukasi Mitigasi Bencana Tanah Longsor bagi Warga Batu Ampar"),
        ("#9 peneliti09", "Disetujui, laporan Monev 1 masih draf, satu luaran tercatat", "Sistem Pemantauan Kualitas Air Sungai Ampal Berbasis IoT"),
        ("#10 peneliti10", "Disetujui, laporan Monev 1 terkirim, menunggu Penilai Luar", "Penguatan Kapasitas Kelompok Nelayan dalam Pengolahan Hasil Tangkap"),
        ("#11 peneliti11", "Ditolak", "Analisis Kestabilan Lereng Jalan Tol Balikpapan Samarinda"),
        ("#12 peneliti12", "Ditarik Kembali (sesudah disetujui)", "Pemberdayaan Bank Sampah Berbasis Aplikasi di Kelurahan Graha Indah"),
        ("Tahun lalu", "peneliti01 sampai 03: Disetujui (Monev 1 sudah dinilai). peneliti04: Ditolak", "Periode tahun lalu"),
    ]
    r += 2
    for values in pengajuan:
        body(ws, r, list(values), bold_cols=(1,))
        r += 1
    r += 1
    notes = [
        "Nomor ganjil memakai formulir Penelitian (skema Penelitian Dasar atau Terapan); nomor genap formulir Pengabdian.",
        "Skema: Penelitian Dasar (kode QA-PD, pagu Rp75.000.000, TKT 1-3), Penelitian Terapan (QA-PT, Rp150.000.000, TKT 4-6), Pengabdian kepada Masyarakat (QA-PKM, Rp50.000.000). Minimal 2 penilai, maksimal 3 mahasiswa.",
        "latar-penilai1 dan latar-penilai2 memegang sekitar 20 penilaian (di atas batas 10): peringatan beban memang benar.",
        "qa{k}-peneliti-baru dibuat langsung oleh seeder, jadi Operator TIDAK menerima notifikasi pendaftar untuknya; untuk menguji notifikasi itu, daftarkan akun baru (alur A02).",
    ]
    for note in notes:
        ws.cell(row=r, column=1, value="Catatan").font = BOLD
        cell = ws.cell(row=r, column=2, value=note)
        cell.font = BODY
        cell.alignment = WRAP
        ws.row_dimensions[r].height = 30
        r += 1


def sheet_cakupan(wb: Workbook, cases: list[dict], scenarios: list[dict]) -> None:
    ws = wb.create_sheet("Cakupan BDD")
    pending = pending_ids()
    covered: dict[str, list[str]] = {}
    for case in cases:
        for ref in case["refs"]:
            covered.setdefault(ref, []).append(case["id"])
    manual = sum(1 for s in scenarios if s["id"] in covered)
    row = title(ws, "Cakupan Skenario BDD",
                f"{len(scenarios)} skenario; {manual} diuji manual di matriks ini. Sisanya diuji otomatis oleh Pest "
                "(waktu, tenggat, konkurensi, job) kecuali yang ditandai belum punya test.")
    header(ws, row, ["ID skenario", "Judul", "Jenis", "Berkas", "Kasus uji manual", "Test otomatis"],
           [20, 70, 13, 36, 22, 22])
    for s in scenarios:
        row += 1
        auto = "Belum ada (pending.txt)" if s["id"] in pending else "Ada (Pest)"
        body(ws, row, [s["id"], s["judul"], s["kelas"], s["berkas"], ", ".join(covered.get(s["id"], [])) or "-", auto],
             bold_cols=(1,))
    ws.conditional_formatting.add(
        f"F5:F{row}", FormulaRule(formula=['LEFT($F5,5)="Belum"'], fill=PatternFill("solid", fgColor="FFE5B4")))
    ws.freeze_panes = "B5"
    ws.auto_filter.ref = f"A4:F{row}"


def main() -> None:
    cases, scenarios = prepare()
    wb = Workbook()
    sheet_petunjuk(wb, cases)
    sheet_kasus(wb, cases)
    sheet_temuan(wb, len(cases))
    sheet_ringkasan(wb)
    sheet_pembagian(wb, cases)
    sheet_akun(wb)
    sheet_cakupan(wb, cases, scenarios)
    for ws in wb.worksheets:
        ws.sheet_view.zoomScale = 100
    OUT.parent.mkdir(parents=True, exist_ok=True)
    wb.save(OUT)
    print(f"{OUT.relative_to(ROOT)}: {len(cases)} kasus, {len(scenarios)} skenario BDD")


if __name__ == "__main__":
    main()
