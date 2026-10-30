import { defineConfig } from "@playwright/test";

/**
 * End-to-end tests for the sales funnel editor (tests/funnel-editor/e2e).
 *
 * Starts its own servers so it never touches development data:
 *  - the Laravel backend through tests/e2e/router.php, which pins the
 *    misron_e2e_test database, on :8001
 *  - the storefront on :3100 (a production build, `next start`), pointed at that backend
 * Uses the installed Google Chrome; no browser download is needed.
 *
 * One-time setup (from misron_backend):
 *   TEST_DB_DATABASE=misron_e2e_test php tests/create_test_database.php
 *   DB_DATABASE=misron_e2e_test php artisan migrate --path=database/migrations/sales_funnel --force
 *
 * Run: npm run test:funnel-editor
 * Unit tests of the shared logic need no servers: npm run test:funnel-core
 */

const BACKEND = "http://127.0.0.1:8001";
const FRONTEND = "http://localhost:3100";

export default defineConfig({
  testDir: "./tests/funnel-editor/e2e",
  timeout: 90_000,
  expect: { timeout: 30_000 },
  workers: 1,
  fullyParallel: false,
  reporter: [["list"]],
  outputDir: "./test-results/funnel-editor",
  use: {
    baseURL: FRONTEND,
    channel: "chrome",
    viewport: { width: 1440, height: 900 },
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  webServer: [
    {
      command: `${process.env.PHP_BINARY || "php"} -S 127.0.0.1:8001 -t public tests/e2e/router.php`,
      cwd: "../misron_backend",
      url: `${BACKEND}/api/v2/funnel-editor/registry`,
      reuseExistingServer: false,
      timeout: 60_000,
      env: { E2E_EDITOR_URL: FRONTEND, FUNNEL_CHECKOUT_ENABLED: "true" },
    },
    {
      // A production build served by next start (see tests/funnel-editor/server.mjs).
      command: "node tests/funnel-editor/server.mjs",
      url: `${FRONTEND}/api/v2/funnel-editor/registry`,
      reuseExistingServer: false,
      timeout: 600_000,
      env: {
        BACKEND_API_URL: `${BACKEND}/api/v2`,
        NEXT_PUBLIC_API_URL: `${FRONTEND}/api/v2`,
        NEXT_PUBLIC_SITE_URL: FRONTEND,
        NEXT_PUBLIC_FILE_BASE_URL: BACKEND,
        IMAGE_URLS: BACKEND,
        NEXT_PUBLIC_FACEBOOK_PIXEL_ID: "",
        NEXT_PUBLIC_GTM_ID: "",
        META_CONVERSIONS_API_TOKEN: "",
        // The backend tells this server to refresh a page when it is published or unpublished.
        FUNNEL_REVALIDATE_SECRET: "e2e-revalidate-secret",
      },
    },
  ],
});
