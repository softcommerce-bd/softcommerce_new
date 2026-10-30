"use client";

import toast from "react-hot-toast";
import { findNode } from "../../core/tree.js";
import { addBundleItems, addProducts, updateBinding } from "../operations.js";
import { useEditor } from "../useEditorState.js";
import ProductPicker from "./ProductPicker.jsx";

/**
 * Opens the product picker for whatever the editor asked for: products for a
 * block (also right after a product block is added), more products for an
 * existing bundle, or the free gift of an offer.
 */
export default function PickerHost() {
  const { state, actions } = useEditor();
  const picker = state.picker;
  const node = picker ? findNode(state.doc, picker.nodeId)?.node : null;
  if (!picker || !node) return null;

  const range = state.registry.blocks[node.type]?.bindings ?? [0, 40];
  const bindings = node.productBindings ?? [];
  const existingIds = bindings.flatMap((binding) => binding.items.map((item) => item.productId));
  const close = () => actions.closePicker();

  let config;
  if (picker.purpose === "gift") {
    config = { max: 1, title: "Choose the free gift", confirmLabel: "Use as gift" };
  } else if (picker.purpose === "bundle") {
    const bundle = bindings.find((binding) => binding.key === picker.key);
    config = { max: Math.max(1, 10 - (bundle?.items.length ?? 0)), title: "Add products to the bundle", confirmLabel: "Add to bundle" };
  } else if (node.type === "product_card") {
    config = { max: 1, title: "Choose the product for this card", confirmLabel: "Use this product" };
  } else if (node.type === "bundle_offer_selector") {
    config = { max: 10, title: "Choose the products in this bundle", confirmLabel: "Create bundle" };
  } else {
    config = { max: Math.max(1, range[1] - bindings.length), title: "Choose products", confirmLabel: "Add to page" };
  }

  const confirm = (picked) => {
    if (picker.purpose === "gift") {
      const [gift] = picked;
      actions.edit((draft) => updateBinding(draft, node.id, picker.key, (binding) => {
        binding.offer = { ...(binding.offer ?? { type: "free_gift" }), giftProductId: gift.productId };
        if (gift.variant) binding.offer.giftVariant = gift.variant;
        else delete binding.offer.giftVariant;
      }));
    } else if (picker.purpose === "bundle") {
      actions.edit((draft) => addBundleItems(draft, node.id, picker.key, picked));
    } else {
      actions.edit((draft) => addProducts(draft, state.registry, node.id, picked));
      const repeats = node.type === "product_offer_selector" ? picked.filter((p) => existingIds.includes(p.productId)).length : 0;
      if (repeats) toast(`${repeats} product${repeats === 1 ? " was" : "s were"} already on this block and skipped`);
    }
    close();
  };

  return <ProductPicker open onClose={close} onConfirm={confirm} existingIds={existingIds} {...config} />;
}
