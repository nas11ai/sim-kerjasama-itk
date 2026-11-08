// tests/e2e/smoke.spec.ts
import { test, expect, type Locator, type Page } from '@playwright/test'

test('halaman login bisa dibuka', async ({ page }) => {
    await page.goto('/login')
    await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible()
})

const login = async (page: Page, email: string) => {
    await page.goto('/login')
    await page.fill('#email', email)
    await page.fill('#password', email)
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.click('button[type="submit"]'),
    ])
}

const logout = (page: Page) => page.context().clearCookies()

// OptionSelect (reka-ui Select): buka pemicunya, lalu pilih opsinya.
const choose = async (page: Page, trigger: string | Locator, option: string | RegExp) => {
    await (typeof trigger === 'string' ? page.locator(trigger) : trigger).click()
    await page.getByRole('option', { name: option }).click()
}

// PNG 1×1 untuk foto profil (BR-IAM-03: foto wajib sebelum membuat pengajuan).
const PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
    'base64'
)

// Alur kritis §5 dari awal: peneliti mengisi wizard sampai submit, Operator menugaskan dua
// reviewer, keduanya mengirim evaluasi tanpa ulir, pengajuan disetujui otomatis (BR-REV-05),
// peneliti melihat "Disetujui".
// Butuh EndToEndFlowSeeder saja (tanpa fixture tambahan): E2E_FIXTURE=full-flow.
test.describe('alur kritis peneliti sampai keputusan', () => {
    test.skip(process.env.E2E_FIXTURE !== 'full-flow', 'butuh EndToEndFlowSeeder')

    test('wizard, dua evaluasi, disetujui, tanpa galat konsol', async ({ page }) => {
        const errors: string[] = []
        page.on('console', (m) => m.type() === 'error' && errors.push(m.text()))
        page.on('pageerror', (e) => errors.push(String(e)))

        await login(page, 'peneliti@itk.ac.id')
        await page.goto('/user/submissions/create')

        // BR-IAM-03 — gerbang kelengkapan profil.
        await page.getByLabel('Nomor Telepon').fill('0812-3456-7890')
        await page.getByLabel('Bidang Keahlian').fill('Rekayasa Perangkat Lunak')
        await page
            .locator('input#profile-photo')
            .setInputFiles({ name: 'foto.png', mimeType: 'image/png', buffer: PNG })
        await page.getByRole('button', { name: 'Simpan Profil' }).click()
        await page.waitForURL(/\/user\/submissions\/create/)

        // Langkah 1 — periode dan form.
        await choose(page, '#start-period', /Periode 2026/)
        await choose(page, '#start-form', 'Pengajuan Penelitian 2026')
        await page.getByRole('button', { name: 'Lanjut' }).click()
        await page.waitForURL(/\/wizard\/2$/)

        // Langkah 2 — jawaban wajib Form.
        await page.getByLabel('Judul Penelitian').fill('Biochar untuk Lahan Gambut Kaltim')
        await page.getByLabel('Abstrak').fill('Penelitian biochar sebagai pembenah tanah gambut.')
        await page.getByRole('button', { name: 'Lanjut' }).click()
        await page.waitForURL(/\/wizard\/3$/)

        // Langkah 3 — skema (BR-SCH-04: batas anggaran dan jumlah reviewer mengikutinya).
        await choose(page, page.getByLabel('Skema Penelitian'), 'Penelitian DIPA 2026')
        await page.getByRole('button', { name: 'Lanjut' }).click()
        await page.waitForURL(/\/wizard\/4$/)

        // Langkah 4 — anggota opsional; ketua sudah tercatat.
        await expect(page.getByText('Ketua', { exact: true })).toBeVisible()
        await page.getByRole('button', { name: 'Lanjut' }).click()
        await page.waitForURL(/\/wizard\/5$/)

        // Langkah 5 — satu item anggaran di bawah batas skema.
        await page.locator('#budget-component').click()
        await page.getByRole('option').first().click()
        await page.locator('#budget-item').fill('Biochar sekam padi')
        await page.locator('#budget-volume').fill('10')
        await page.locator('#budget-unit').fill('kg')
        await page.locator('#budget-price').fill('150000')
        await page.getByRole('button', { name: 'Tambah Item' }).click()
        await expect(page.getByText('Biochar sekam padi')).toBeVisible()
        await page.getByRole('button', { name: 'Lanjut' }).click()
        await page.waitForURL(/\/wizard\/6$/)

        // Langkah 6 — Form ini tanpa field berkas.
        await page.getByRole('button', { name: 'Lanjut' }).click()
        await page.waitForURL(/\/wizard\/7$/)

        // Langkah 7 — submit lewat dialog konfirmasi.
        await expect(page.getByText('Rp 1.500.000 dari Rp 75.000.000')).toBeVisible()
        await page.getByRole('button', { name: 'Submit Pengajuan' }).click()
        await page
            .getByRole('dialog', { name: 'Kirim pengajuan?' })
            .getByRole('button', { name: 'Submit', exact: true })
            .click()
        await page.waitForURL(/\/user\/submissions\/\d+$/)
        const submissionUrl = page.url()
        const submissionId = submissionUrl.split('/').pop()
        await expect(page.getByText('Menunggu Review').first()).toBeVisible()
        await logout(page)

        // Operator — dua reviewer sesuai minimum skema DIPA (BR-REV-01).
        await login(page, 'operator@itk.ac.id')
        await page.goto(`/admin/submissions/${submissionId}`)
        await page.getByRole('button', { name: /Tugaskan Reviewer/ }).click()
        const assign = page.getByRole('dialog')
        await assign.getByRole('button', { name: /Dr\. Sari Wijaya/ }).click()
        await assign.getByRole('button', { name: /Dr\. Andi Mappa/ }).click()
        await expect(assign).toContainText('2 dari minimal 2 reviewer')
        await assign.getByRole('button', { name: 'Tugaskan', exact: true }).click()
        await expect(assign).toBeHidden()
        await logout(page)

        // Kedua reviewer mengirim evaluasi.
        for (const reviewer of ['reviewer1@itk.ac.id', 'reviewer2@itk.ac.id']) {
            await login(page, reviewer)
            await page.goto('/reviewer/assignments')
            await page
                .getByRole('link', { name: /Mulai Evaluasi/ })
                .first()
                .click()
            await page.getByLabel('Catatan Kualitatif').fill('Metodologi kuat, layak didanai.')
            await page.getByRole('button', { name: /Submit Evaluasi/ }).click()
            await page.getByRole('dialog').getByRole('button', { name: 'Submit Evaluasi' }).click()
            await expect(page.getByText('Penilaian sudah dikirim')).toBeVisible()
            await logout(page)
        }

        // BR-REV-05 — evaluasi terakhir tanpa ulir terbuka menyetujui otomatis; tidak ada
        // tombol "Setujui" bagi Operator (hanya penolakan yang manual, BR-REV-07).
        await login(page, 'peneliti@itk.ac.id')
        await page.goto(submissionUrl)
        await expect(page.getByText('Disetujui').first()).toBeVisible()

        expect(errors).toEqual([])
    })
})

// Alur kritis S4 (fe-v2-design §5). Butuh EndToEndFlowSeeder + tests/e2e/fixtures/operator-flow.php
// di database server, karena itu hanya jalan dengan E2E_FIXTURE=operator-flow.
test.describe('alur Operator S4', () => {
    test.skip(process.env.E2E_FIXTURE !== 'operator-flow', 'butuh fixture operator-flow')

    test('tugaskan dua reviewer, reviewer mengevaluasi, Operator menolak manual', async ({
        page,
    }) => {
        await login(page, 'operator@itk.ac.id')
        await page.goto('/admin/submissions')
        await expect(page.getByRole('heading', { name: 'Semua Pengajuan' })).toBeVisible()
        await page.locator('tbody tr a').first().click()
        await page.waitForURL(/\/admin\/submissions\/\d+$/)
        const detailUrl = page.url()

        await page.getByRole('button', { name: /Tugaskan Reviewer/ }).click()
        const assign = page.getByRole('dialog')
        await assign.getByRole('button', { name: /Dr\. Sari Wijaya/ }).click()
        await assign.getByRole('button', { name: /Dr\. Andi Mappa/ }).click()
        await expect(assign).toContainText('2 dari minimal 2 reviewer')
        await assign.getByRole('button', { name: 'Tugaskan', exact: true }).click()
        await expect(assign).toBeHidden()
        await expect(page.getByText('Sedang Direview').first()).toBeVisible()
        await logout(page)

        await login(page, 'reviewer1@itk.ac.id')
        await page.goto('/reviewer/assignments')
        await page
            .getByRole('link', { name: /Mulai Evaluasi/ })
            .first()
            .click()
        // Penugasan Operator memakai auto_assign_forms, jadi form evaluasinya sudah dimulai.
        await page.getByLabel('Catatan Kualitatif').fill('Metodologi kuat, perjelas jadwal.')
        await page.getByRole('button', { name: /Submit Evaluasi/ }).click()
        await page.getByRole('dialog').getByRole('button', { name: 'Submit Evaluasi' }).click()
        await expect(page.getByText('Penilaian sudah dikirim')).toBeVisible()
        await logout(page)

        await login(page, 'operator@itk.ac.id')
        await page.goto(detailUrl)
        await page.getByRole('button', { name: /Tolak Manual/ }).click()
        await page.locator('#reject-reason').fill('Plagiarisme terbukti pada bab metodologi.')
        await page.getByRole('button', { name: 'Tolak Pengajuan' }).click()
        await page
            .getByRole('dialog', { name: 'Tolak pengajuan ini?' })
            .getByRole('button', { name: 'Tolak', exact: true })
            .click()
        await expect(page.getByText('Ditolak').first()).toBeVisible()
    })
})
