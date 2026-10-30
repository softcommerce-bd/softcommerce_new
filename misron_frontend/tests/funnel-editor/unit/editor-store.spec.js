import { expect, test } from "@playwright/test";
import registry from "../fixtures/registry.json" with { type: "json" };
import { deleteBlock, duplicateBlock, insertBlock, moveBlock, setStyle } from "../../../funnel/editor/operations.js";
import { createInitialState, editorReducer, hasUnsavedWork, SAVE } from "../../../funnel/editor/store.js";

function initial(permissions = { edit_funnel: true, publish_funnel: true }) {
  return createInitialState({
    funnel: { id: 7, title: "Offer", slug: "offer", status: "draft", draft: { content: { schemaVersion: 1, page: { sections: [] } }, revision: 3 } },
    registry,
    user: { id: 1, permissions },
  });
}

const edit = (state, recipe, extra = {}) => editorReducer(state, { type: "edit", recipe, ...extra });

test("insert, move, duplicate and delete through the reducer", () => {
  let state = initial();
  state = edit(state, (d) => insertBlock(d, registry, { type: "section", parentId: null, index: 0, preset: { children: [] } }), { select: "result" });
  const sectionId = state.selectedId;
  expect(state.doc.page.sections[0].type).toBe("section");

  state = edit(state, (d) => insertBlock(d, registry, { type: "heading", parentId: sectionId, index: 0, preset: { props: { text: "শিরোনাম" } } }), { select: "result" });
  const headingId = state.selectedId;
  expect(state.doc.page.sections[0].children[0].props.text).toBe("শিরোনাম");

  state = edit(state, (d) => duplicateBlock(d, registry, headingId), { select: "result" });
  expect(state.doc.page.sections[0].children).toHaveLength(2);
  expect(state.selectedId).not.toBe(headingId);

  state = edit(state, (d) => moveBlock(d, registry, { id: headingId, parentId: sectionId, index: 2 }), { select: headingId });
  expect(state.doc.page.sections[0].children[1].id).toBe(headingId);

  state = edit(state, (d) => deleteBlock(d, headingId), { select: "result" });
  expect(state.doc.page.sections[0].children).toHaveLength(1);
  expect(state.selectedId).toBe(state.doc.page.sections[0].children[0].id);
  expect(state.version).toBe(5);
});

test("blocks dropped at the top level are wrapped in a section", () => {
  let state = initial();
  state = edit(state, (d) => insertBlock(d, registry, { type: "heading", parentId: null, index: 0, wrap: true }), { select: "result" });
  expect(state.doc.page.sections[0].type).toBe("section");
  expect(state.doc.page.sections[0].children[0].type).toBe("heading");

  // Content blocks that may stand alone still get a section for spacing;
  // full-width heroes do not.
  state = edit(state, (d) => insertBlock(d, registry, { type: "faq_accordion", parentId: null, index: 1 }));
  state = edit(state, (d) => insertBlock(d, registry, { type: "promo_hero", parentId: null, index: 2 }));
  expect(state.doc.page.sections.map((s) => s.type)).toEqual(["section", "section", "promo_hero"]);
  expect(state.doc.page.sections[1].children[0].type).toBe("faq_accordion");
});

test("refused edits leave the document alone and report why", () => {
  let state = initial();
  state = edit(state, (d) => insertBlock(d, registry, { type: "sticky_cta", parentId: null, index: 0 }));
  const before = state.doc;
  state = edit(state, (d) => insertBlock(d, registry, { type: "sticky_cta", parentId: null, index: 1 }));
  expect(state.doc).toBe(before);
  expect(state.lastError.code).toBe("limit");
});

test("undo and redo, with typing merged into one step", () => {
  let state = initial();
  state = edit(state, (d) => insertBlock(d, registry, { type: "section", parentId: null, index: 0, preset: { children: [] } }), { select: "result" });
  const id = state.selectedId;
  for (const text of ["ঈ", "ঈদ", "ঈদ অফার"]) {
    state = edit(state, (d) => {
      d.page.sections[0].props = { contentWidth: "boxed" };
      d.page.sections[0].advanced = { label: text };
      return { id };
    }, { mergeKey: "label" });
  }
  expect(state.past).toHaveLength(2);
  state = editorReducer(state, { type: "undo" });
  expect(state.doc.page.sections[0].advanced).toBeUndefined();
  state = editorReducer(state, { type: "redo" });
  expect(state.doc.page.sections[0].advanced.label).toBe("ঈদ অফার");
  state = editorReducer(state, { type: "undo" });
  state = editorReducer(state, { type: "undo" });
  expect(state.doc.page.sections).toHaveLength(0);
});

test("style values per device clean up after themselves", () => {
  let state = initial();
  state = edit(state, (d) => insertBlock(d, registry, { type: "section", parentId: null, index: 0, preset: { children: [] } }), { select: "result" });
  const id = state.selectedId;
  state = edit(state, (d) => setStyle(d, id, "mobile", "paddingTop", 12));
  expect(state.doc.page.sections[0].style).toEqual({ mobile: { paddingTop: 12 } });
  state = edit(state, (d) => setStyle(d, id, "mobile", "paddingTop", undefined));
  expect(state.doc.page.sections[0].style).toBeUndefined();
});

test("save bookkeeping never drops an edit made during a save", () => {
  let state = initial();
  state = edit(state, (d) => insertBlock(d, registry, { type: "section", parentId: null, index: 0 }));
  expect(state.save.status).toBe(SAVE.dirty);
  const sentVersion = state.version;
  state = editorReducer(state, { type: "save/start" });
  // The merchant keeps typing while the request is in flight.
  state = edit(state, (d) => insertBlock(d, registry, { type: "section", parentId: null, index: 1 }));
  state = editorReducer(state, { type: "save/success", revision: 4, version: sentVersion });
  expect(state.revision).toBe(4);
  expect(state.save.status).toBe(SAVE.dirty);
  expect(hasUnsavedWork(state)).toBe(true);
  state = editorReducer(state, { type: "save/success", revision: 5, version: state.version });
  expect(state.save.status).toBe(SAVE.saved);
  expect(hasUnsavedWork(state)).toBe(false);
});

test("read-only users cannot change anything", () => {
  let state = initial({ view_funnels: true });
  const before = state;
  state = edit(state, (d) => insertBlock(d, registry, { type: "section", parentId: null, index: 0 }));
  expect(state).toBe(before);
  expect(state.canPublish).toBe(false);
});
