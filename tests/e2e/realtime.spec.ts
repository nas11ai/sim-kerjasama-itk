// tests/e2e/realtime.spec.ts
//
// RT1 — logout seketika dan kehadiran langsung, di browser sungguhan. Butuh EndToEndFlowSeeder,
// server dengan BROADCAST_CONNECTION=reverb, `php artisan reverb:start`, `php artisan queue:work`,
// dan aset yang dibangun dengan VITE_REVERB_*; karena itu hanya jalan dengan E2E_FIXTURE=realtime.
import { test, expect, type Browser, type Page } from '@playwright/test'

test.describe.configure({ mode: 'serial' })
test.skip(process.env.E2E_FIXTURE !== 'realtime', 'butuh Reverb dan worker antrean')

const errors: string[] = []

async function signIn(browser: Browser, email: string): Promise<Page> {
    const page = await (await browser.newContext()).newPage()
    page.on('console', (m) => m.type() === 'error' && errors.push(`${email}: ${m.text()}`))
    page.on('pageerror', (e) => errors.push(`${email}: ${String(e)}`))

    await page.goto('/login')
    await page.fill('#email', email)
    await page.fill('#password', email)
    // Kanal privat user diotorisasi setelah halaman pasca-login dimuat.
    const authorized = page.waitForResponse(
        (r) => r.url().endsWith('/broadcasting/auth') && r.status() === 200
    )
    await page.click('button[type="submit"]')
    await page.waitForURL((url) => !url.pathname.startsWith('/login'))
    await authorized

    return page
}

async function openUser(admin: Page, name: string): Promise<void> {
    await admin.goto(`/admin/users?search=${encodeURIComponent(name)}`)
    await admin
        .locator('tbody tr', { hasText: name })
        .getByRole('link', { name: /Detail/ })
        .click()
    await admin.waitForURL(/\/admin\/users\/\d+$/)
}

test('[IAM-DEACT-09] tab user yang dinonaktifkan langsung ke login', async ({ browser }) => {
    const sari = await signIn(browser, 'reviewer1@itk.ac.id')
    const admin = await signIn(browser, 'operator@itk.ac.id')

    await openUser(admin, 'Dr. Sari Wijaya')
    await admin.getByRole('button', { name: 'Nonaktifkan' }).click()
    await admin.getByRole('dialog').getByRole('button', { name: 'Nonaktifkan' }).click()
    await expect(admin.getByText('Nonaktif', { exact: true }).first()).toBeVisible()

    await sari.waitForURL(/\/login\?sesi=nonaktif/, { timeout: 10_000 })
    await expect(sari.getByTestId('session-notice')).toHaveText(
        'Akun Anda dinonaktifkan. Hubungi LPPM bila ini keliru.'
    )
})

test('[IAM-SESSION-01] tab user yang sesinya diakhiri langsung ke login', async ({ browser }) => {
    const andi = await signIn(browser, 'reviewer2@itk.ac.id')
    const admin = await signIn(browser, 'operator@itk.ac.id')

    await openUser(admin, 'Dr. Andi Mappa')
    await admin.getByRole('button', { name: 'Akhiri Semua Sesi' }).click()
    await admin.getByRole('dialog').getByRole('button', { name: 'Akhiri Semua Sesi' }).click()

    await andi.waitForURL(/\/login\?sesi=diakhiri/, { timeout: 10_000 })
    await expect(andi.getByTestId('session-notice')).toHaveText(
        'Sesi Anda diakhiri oleh admin. Silakan masuk lagi.'
    )
})

test('[IAM-SESSION-02] badge Sedang aktif menyala tanpa reload', async ({ browser }) => {
    const admin = await signIn(browser, 'operator@itk.ac.id')
    const activityChannel = admin.waitForResponse(
        (r) => r.url().endsWith('/broadcasting/auth') && r.status() === 200
    )
    await admin.goto('/admin/users?search=Budi')
    await activityChannel
    const row = admin.locator('tbody tr', { hasText: 'Dr. Budi Santoso' })
    await expect(row).not.toContainText('Sedang aktif')

    await signIn(browser, 'peneliti@itk.ac.id')

    await expect(row).toContainText('Sedang aktif', { timeout: 10_000 })
})

test('tanpa galat konsol', () => {
    expect(errors).toEqual([])
})
