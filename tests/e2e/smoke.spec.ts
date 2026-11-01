// tests/e2e/smoke.spec.ts
import { test, expect, type Page } from '@playwright/test'

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
