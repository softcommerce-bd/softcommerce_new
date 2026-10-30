import { expect, test } from "@playwright/test";
import fixture from "../fixtures/registry.json" with { type: "json" };
import { createFunnel, openEditor, seed, USERS } from "./helpers.js";

test("without a session the editor explains how to open it", async ({ page }) => {
  const funnel = createFunnel("Access: no session");
  await page.goto(`/builder/funnels/${funnel.id}`);
  await expect(page.getByTestId("editor-access")).toHaveAttribute("data-reason", "no-session");
  await expect(page.getByTestId("funnel-editor")).toHaveCount(0);
});

test("a handoff code signs in once and disappears from the address bar", async ({ page, context }) => {
  const funnel = createFunnel("Access: handoff");
  const url = await openEditor(page, funnel.id);
  expect(page.url()).not.toContain("handoff=");

  // A second tab replaying the same link (from history, a shared screenshot…) is refused.
  const second = await context.newPage();
  await second.goto(url);
  await expect(second.getByTestId("editor-access")).toHaveAttribute("data-reason", "expired-link");
});

test("customers cannot get an editor session", async ({ page }) => {
  const funnel = createFunnel("Access: customer");
  const { url } = seed("handoff", USERS.customer, funnel.id);
  await page.goto(url);
  await expect(page.getByTestId("editor-access")).toHaveAttribute("data-reason", "expired-link");
});

test("view-only staff get a read-only editor", async ({ page }) => {
  const funnel = createFunnel("Access: viewer");
  await openEditor(page, funnel.id, "viewer");
  await expect(page.getByTestId("read-only-banner")).toBeVisible();
  await expect(page.getByTestId("library-section")).toBeDisabled();
  // The dialog opens for looking, but offers no way to publish.
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-dialog")).toContainText("cannot publish");
  await expect(page.getByTestId("publish-confirm")).toHaveCount(0);
  await page.keyboard.press("Escape");
  await expect(page.getByTestId("save")).toHaveCount(0);
});

test("staff without publish permission cannot publish, even through the API", async ({ page }) => {
  const funnel = createFunnel("Access: editor role");
  await openEditor(page, funnel.id, "editor");
  await page.getByTestId("publish").click();
  await expect(page.getByTestId("publish-confirm")).toHaveCount(0);
  await page.keyboard.press("Escape");
  await expect(page.getByTestId("library-section")).toBeEnabled();

  const status = await page.evaluate(async (id) => {
    const session = JSON.parse(sessionStorage.getItem("misron:funnel-editor-session"));
    const response = await fetch(`/api/v2/funnel-editor/funnels/${id}/publish`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${session.token}` },
    });
    return response.status;
  }, funnel.id);
  expect(status).toBe(403);
  expect(seed("show", funnel.id).status).toBe("draft");
});

test("the editor API refuses requests without an editor token", async ({ request }) => {
  expect((await request.get("/api/v2/funnel-editor/funnels")).status()).toBe(401);
  expect((await request.post("/api/v2/funnel-editor/session", { data: { code: "guess" } })).status()).toBe(401);
});

test("the unit test registry fixture matches the server", async ({ page }) => {
  const funnel = createFunnel("Access: registry");
  await openEditor(page, funnel.id);
  const live = await page.evaluate(async () => {
    const session = JSON.parse(sessionStorage.getItem("misron:funnel-editor-session"));
    const response = await fetch("/api/v2/funnel-editor/registry", { headers: { Accept: "application/json", Authorization: `Bearer ${session.token}` } });
    return (await response.json()).registry;
  });
  expect(live).toEqual(fixture);
});
