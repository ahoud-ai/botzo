// @ts-check
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Playwright',
    timeout: 120000,
    use: {
        headless: true,
        ignoreHTTPSErrors: true,
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
        viewport: {
            width: 1600,
            height: 1100,
        },
    },
});
