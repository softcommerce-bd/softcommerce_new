import { sectionsOf, walk } from "./tree.js";

/**
 * Setup state of a page made from a template.
 *
 * A template names "slots": places that need a product. Each slot lists the
 * offers it fills (`bindings`: offer key -> pieces per offer). A slot is done
 * when every one of its offers has a product.
 */

/** @returns {Record<string, boolean>} slot key -> every offer of the slot has a product */
export function slotState(document, slots = []) {
  const filled = new Map();
  walk(sectionsOf(document), (node) => {
    for (const binding of node.productBindings ?? []) {
      filled.set(binding.key, Array.isArray(binding.items) && binding.items.length > 0);
    }
  });

  const state = {};
  for (const slot of slots) {
    const keys = Object.keys(slot.bindings ?? {});
    state[slot.key] = keys.length > 0 && keys.every((key) => filled.get(key) === true);
  }
  return state;
}

/** The product ids behind a slot (first offer's first item), or null when empty. */
export function slotProduct(document, slot) {
  const first = Object.keys(slot.bindings ?? {})[0];
  let found = null;
  walk(sectionsOf(document), (node) => {
    for (const binding of node.productBindings ?? []) {
      if (binding.key === first && binding.items?.[0]) found = binding.items[0].productId;
    }
  });
  return found;
}

/** Slots still waiting for a product. */
export function unfilledSlots(document, slots = []) {
  const state = slotState(document, slots);
  return slots.filter((slot) => !state[slot.key]);
}
