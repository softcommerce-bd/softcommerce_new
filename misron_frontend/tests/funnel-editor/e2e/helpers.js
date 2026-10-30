import { execFileSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { expect } from "@playwright/test";

/**
 * Fixtures come from the backend's tests/e2e/seed.php, which only writes to
 * the misron_e2e_test database.
 */
const BACKEND_DIR = path.resolve(process.cwd(), "../misron_backend");
const PHP = process.env.PHP_BINARY || "php";

export function seed(...args) {
  const output = execFileSync(PHP, ["tests/e2e/seed.php", ...args.map(String)], { cwd: BACKEND_DIR });
  return JSON.parse(output.toString());
}

export const USERS = {
  admin: "e2e-admin@funnel.test",
  editor: "e2e-editor@funnel.test",
  viewer: "e2e-viewer@funnel.test",
  customer: "e2e-customer@funnel.test",
};

export function createFunnel(title = "E2E funnel") {
  seed("users");
  return seed("funnel", title);
}

/** Opens the editor the way the admin panel does: with a one-time handoff code. */
export async function openEditor(page, funnelId, who = "admin") {
  const { url } = seed("handoff", USERS[who], funnelId);
  await page.goto(url);
  await expect(page.getByTestId("funnel-editor")).toBeVisible();
  return url;
}

export function serverFunnel(funnelId) {
  return seed("show", funnelId);
}

/** Opens the Publish dialog, waits for its checks, and confirms ("Publish now" or "Update live page"). */
export async function publishNow(page) {
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-dialog")).toBeVisible();
  await expect(page.getByTestId("publish-checking")).toHaveCount(0);
  await page.getByTestId("publish-confirm").click();
}

export async function waitForSaved(page) {
  await expect(page.getByTestId("save-status")).toHaveAttribute("data-status", "saved", { timeout: 20_000 });
}

/**
 * Drags with real pointer events, which is what the editor's drag-and-drop
 * listens to. `target` is a locator; the drop lands at `position` within it
 * (fractions of its width and height).
 */
export async function drag(page, source, target, position = { x: 0.5, y: 0.5 }) {
  // Library items further down the list sit below the fold of their panel.
  await source.scrollIntoViewIfNeeded();
  const from = await source.boundingBox();
  const to = await target.boundingBox();
  if (!from || !to) throw new Error("drag source or target is not visible");
  const startX = from.x + from.width / 2;
  const startY = from.y + from.height / 2;
  const endX = to.x + to.width * position.x;
  const endY = to.y + to.height * position.y;

  await page.mouse.move(startX, startY);
  await page.mouse.down();
  await page.mouse.move(startX + 12, startY + 12, { steps: 4 });
  await page.mouse.move(endX, endY, { steps: 16 });
  await page.mouse.move(endX, endY + 1, { steps: 2 });
  await page.waitForTimeout(120);
  await page.mouse.up();
}

export function canvasNodes(page, type) {
  return page.locator(`[data-testid="canvas-frame"] [data-fn-id^="${type.replace(/_/g, "")}_"]`);
}

/**
 * Creates a funnel from a page document, publishes it, and returns its slug and id.
 * Used to stage live pages quickly; the editor tests cover building them by hand.
 */
export function publishPage(title, document) {
  const funnel = draftPage(title, document);
  const published = seed("publish", funnel.id);
  return { id: funnel.id, slug: published.slug, warnings: published.warnings };
}

/** A draft funnel holding the given page document. */
export function draftPage(title, document) {
  seed("users");
  const file = path.join(os.tmpdir(), `funnel-${Date.now()}-${Math.random().toString(36).slice(2)}.json`);
  fs.writeFileSync(file, JSON.stringify(document));
  try {
    return seed("funnel", title, file);
  } finally {
    fs.rmSync(file, { force: true });
  }
}
