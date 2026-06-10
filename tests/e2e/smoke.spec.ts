// tests/e2e/smoke.spec.ts
import { test, expect } from '@playwright/test'

test('halaman login bisa dibuka', async ({ page }) => {
    await page.goto('/login')
    await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible()
})
