import { expect, test } from "@playwright/test";
import { canvasNodes, draftPage, openEditor, seed, serverFunnel, waitForSaved } from "./helpers.js";

/**
 * Saved sections: save a section from one funnel, find it, look at it, add it
 * to another funnel, copy and delete it.
 */

function pageWith(headline) {
  return {
    schemaVersion: 1,
    page: {
      sections: [
        { id: "hero_001", type: "section", children: [{ id: "heading_001", type: "heading", props: { text: headline, level: "h1" } }, { id: "text_001", type: "text", props: { text: "Free delivery all over Bangladesh" } }] },
      ],
    },
  };
}

test.beforeAll(() => {
  seed("users");
  seed("saved-sections-clear");
});

test("a section saved on one page can be found, previewed, added to another page, copied and deleted", async ({ page }) => {
  test.setTimeout(120_000);
  const source = draftPage("Saved: source", pageWith("Eid mega hero"));
  await openEditor(page, source.id);

  // Saving is offered for top-level sections only.
  await canvasNodes(page, "heading").first().click();
  await expect(page.getByTestId("save-section")).toHaveCount(0);
  await page.getByTestId("breadcrumbs").getByRole("button", { name: "Section" }).click();
  await page.getByTestId("save-section").click();
  await expect(page.getByTestId("save-section-confirm")).toBeDisabled();
  await page.getByTestId("save-section-name").fill("Eid hero");
  await page.getByTestId("save-section-confirm").click();
  await expect(page.getByText("Saved. Find it under Saved")).toBeVisible();

  // Another funnel: the section is in its Saved tab.
  const target = draftPage("Saved: target", pageWith("Different page"));
  await openEditor(page, target.id);
  await page.getByTestId("left-tab-saved").click();
  await expect(page.getByTestId("saved-item")).toHaveCount(1);
  await page.getByTestId("saved-search").fill("nothing like this");
  await expect(page.getByTestId("saved-empty")).toContainText("No saved section matches");
  await page.getByTestId("saved-search").fill("eid");
  await expect(page.getByTestId("saved-item")).toHaveCount(1);

  await page.getByTestId("saved-preview").click();
  const preview = page.getByTestId("saved-preview-dialog");
  await expect(preview.getByRole("heading", { name: "Eid mega hero" })).toBeVisible();
  await page.getByTestId("saved-preview-insert").click();
  await expect(canvasNodes(page, "heading")).toHaveCount(2);
  await waitForSaved(page);
  const sections = serverFunnel(target.id).content.page.sections;
  expect(sections).toHaveLength(2);
  expect(sections[1].children[0].props.text).toBe("Eid mega hero");
  // A fresh copy: its ids do not clash with the page's own blocks.
  expect(sections[1].id).not.toBe(sections[0].id);

  // Add it again straight from the list.
  await page.getByTestId("saved-insert").click();
  await expect(canvasNodes(page, "heading")).toHaveCount(3);

  await page.getByTestId("saved-search").fill("");
  await page.getByTestId("saved-duplicate").click();
  await expect(page.getByTestId("saved-item")).toHaveCount(2);
  await expect(page.locator('[data-testid="saved-item"][data-name="Eid hero (copy)"]')).toBeVisible();

  await page.locator('[data-testid="saved-item"][data-name="Eid hero (copy)"]').getByTestId("saved-delete").click();
  await page.getByTestId("saved-delete-confirm").click();
  await expect(page.getByTestId("saved-item")).toHaveCount(1);
});

test("an empty list explains how to save a section", async ({ page }) => {
  seed("saved-sections-clear");
  const funnel = draftPage("Saved: empty", pageWith("Hello"));
  await openEditor(page, funnel.id);
  await page.getByTestId("left-tab-saved").click();
  await expect(page.getByTestId("saved-empty")).toContainText("No saved sections yet");
});
