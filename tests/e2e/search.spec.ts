// tests/e2e/search.spec.ts
//
// K2 — pencarian global topbar di browser sungguhan. Butuh EndToEndFlowSeeder: E2E_FIXTURE=full-flow.
import { expect, test, type Page } from '@playwright/test'

test.skip(process.env.E2E_FIXTURE !== 'full-flow', 'butuh EndToEndFlowSeeder')

const login = async (page: Page, email: string) => {
    await page.goto('/login')
    await page.fill('#email', email)
    await page.fill('#password', email)
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.click('button[type="submit"]'),
    ])
}

const trackErrors = (page: Page) => {
    const errors: string[] = []
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()))
    page.on('pageerror', (e) => errors.push(String(e)))

    return errors
}

const palette = (page: Page) => page.getByRole('dialog')

test('Operator: Ctrl+K, cari budi, Enter membuka detail user', async ({ page }) => {
    const errors = trackErrors(page)
    await login(page, 'operator@itk.ac.id')
    await page.goto('/dashboard')
    // Halaman Inertia di-mount setelah event load; shortcut baru terpasang bersama topbar.
    await expect(page.getByRole('button', { name: /Cari halaman, pengajuan, user/ })).toBeVisible()

    await page.keyboard.press('Control+k')
    await expect(palette(page)).toBeVisible()
    await page.keyboard.type('budi')

    const userOption = palette(page)
        .getByRole('group')
        .filter({ hasText: /^User/ })
        .getByRole('option', { name: /Dr\. Budi Santoso/ })
    await expect(userOption).toBeVisible()

    // Sorot opsi user dengan panah lalu Enter (grup Pengajuan bisa berada di atasnya).
    const index = await userOption.evaluate((el) =>
        Array.from(
            el.closest('[role="listbox"]')?.querySelectorAll('[role="option"]') ?? []
        ).indexOf(el)
    )
    expect(index).toBeGreaterThanOrEqual(0)
    for (let i = 0; i < index; i++) await page.keyboard.press('ArrowDown')
    await expect(userOption).toHaveAttribute('data-highlighted', '')
    await page.keyboard.press('Enter')

    await page.waitForURL(/\/admin\/users\/\d+$/)
    await expect(palette(page)).toBeHidden()
    expect(errors).toEqual([])
})

test('Peneliti: navigasi tersaring izin dan tanpa grup User', async ({ page }) => {
    const errors = trackErrors(page)
    await login(page, 'peneliti@itk.ac.id')
    await page.goto('/dashboard')

    await page.getByRole('button', { name: /Cari halaman, pengajuan, user/ }).click()
    await page.keyboard.type('pengumuman')

    await expect(
        palette(page).getByRole('option', { name: 'Pengumuman', exact: true })
    ).toBeVisible()
    await expect(palette(page).getByRole('option', { name: 'Kelola Pengumuman' })).toHaveCount(0)
    // Respons server (grup kosong) sudah datang: pesan memuat hilang dari live region.
    await expect(palette(page).getByRole('status')).not.toHaveText('Mencari…')
    await expect(palette(page).getByText('Pengguna & Verifikasi', { exact: true })).toHaveCount(0)
    expect(errors).toEqual([])
})
