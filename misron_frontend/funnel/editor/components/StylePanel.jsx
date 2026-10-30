"use client";

import { Monitor, RotateCcw, Smartphone, Tablet } from "lucide-react";
import { resolveStyle, valueSource } from "../../core/breakpoints.js";
import { cx } from "../../core/values.js";
import { useEditor } from "../useEditorState.js";
import { ColorField, NumberField, SelectField, ToggleField } from "./fields.jsx";

/**
 * Style controls. Values apply to the screen size shown in the toolbar; a
 * value set on a larger screen carries down until it is changed here, and
 * the field shows where an inherited value comes from.
 */

const DEVICE_ICON = { desktop: Monitor, tablet: Tablet, mobile: Smartphone };
const DEVICE_NAME = { desktop: "Desktop", tablet: "Tablet", mobile: "Mobile" };

const px = (key, label, min, max, step = 1) => ({ key, label, kind: "number", min, max, step, unit: "px" });
const select = (key, label, options) => ({ key, label, kind: "select", options: [["", "Default"], ...options] });
const color = (key, label) => ({ key, label, kind: "color" });

const ALIGN = select("textAlign", "Alignment", [["left", "Left"], ["center", "Centre"], ["right", "Right"], ["justify", "Justify"]]);
const WEIGHTS = select("fontWeight", "Weight", [["300", "Light"], ["400", "Regular"], ["500", "Medium"], ["600", "Semibold"], ["700", "Bold"], ["800", "Extra bold"], ["900", "Black"]]);
const RADIUS = px("borderRadius", "Corner radius", 0, 200);
const SHADOW = select("shadow", "Shadow", [["none", "None"], ["sm", "Small"], ["md", "Medium"], ["lg", "Large"]]);
const ASPECT = select("aspectRatio", "Shape", [["auto", "Original"], ["1/1", "Square"], ["4/3", "4:3"], ["3/4", "3:4 portrait"], ["16/9", "16:9 wide"], ["9/16", "9:16 story"], ["3/2", "3:2"], ["2/3", "2:3"]]);
const FIT = select("objectFit", "Fit", [["cover", "Fill (crop)"], ["contain", "Fit (no crop)"]]);
const GAP = px("gap", "Space between", 0, 120);
const JUSTIFY = select("justifyContent", "Distribute", [["start", "Start"], ["center", "Centre"], ["end", "End"], ["between", "Spread out"]]);
const ALIGN_ITEMS = select("alignItems", "Align items", [["start", "Start"], ["center", "Centre"], ["end", "End"], ["stretch", "Stretch"]]);
const COLUMNS = { key: "columns", label: "Columns", kind: "number", min: 1, max: 6, step: 1 };

export const STYLE_GROUPS = {
  spacing: {
    title: "Spacing",
    controls: [
      px("paddingTop", "Padding top", 0, 400),
      px("paddingBottom", "Padding bottom", 0, 400),
      px("paddingLeft", "Padding left", 0, 400),
      px("paddingRight", "Padding right", 0, 400),
      px("marginTop", "Margin top", -200, 400),
      px("marginBottom", "Margin bottom", -200, 400),
    ],
  },
  typography: {
    title: "Text",
    controls: [px("fontSize", "Size", 8, 120), WEIGHTS, { key: "lineHeight", label: "Line height", kind: "number", min: 0.8, max: 3, step: 0.05 }, px("letterSpacing", "Letter spacing", -5, 20, 0.1), ALIGN, color("color", "Colour")],
  },
  align: { title: "Alignment", controls: [ALIGN] },
  background: { title: "Background", controls: [color("backgroundColor", "Colour")] },
  border: {
    title: "Border and shadow",
    controls: [
      px("borderWidth", "Border width", 0, 20),
      select("borderStyle", "Border style", [["solid", "Solid"], ["dashed", "Dashed"], ["dotted", "Dotted"], ["none", "None"]]),
      color("borderColor", "Border colour"),
      RADIUS,
      SHADOW,
    ],
  },
  size: { title: "Size", controls: [px("maxWidth", "Maximum width (0 = none)", 0, 2000), { key: "widthPercent", label: "Width", kind: "number", min: 5, max: 100, step: 1, unit: "%" }, px("minHeight", "Minimum height", 0, 1200)] },
  sectionLayout: { title: "Content", controls: [px("maxWidth", "Content width (0 = full)", 0, 2000), GAP, ALIGN_ITEMS] },
  layout: { title: "Layout", controls: [GAP, JUSTIFY, ALIGN_ITEMS] },
  grid: { title: "Grid", controls: [COLUMNS, GAP] },
  gallery: { title: "Gallery", controls: [COLUMNS, GAP] },
  columnWidth: { title: "Width", controls: [{ key: "widthPercent", label: "Column width", kind: "number", min: 5, max: 100, step: 1, unit: "%" }] },
  height: { title: "Height", controls: [px("height", "Height", 0, 1200)] },
  image: { title: "Image", controls: [ASPECT, FIT, { key: "widthPercent", label: "Width", kind: "number", min: 5, max: 100, step: 1, unit: "%" }, px("maxWidth", "Maximum width (0 = none)", 0, 2000), RADIUS, SHADOW] },
  bannerImage: { title: "Image", controls: [ASPECT, FIT, px("height", "Height", 0, 1200), RADIUS] },
  heroMedia: { title: "Image", controls: [ASPECT, FIT, RADIUS, SHADOW] },
  productGrid: { title: "Grid", controls: [COLUMNS, GAP] },
  productCard: {
    title: "Card",
    controls: [
      color("cardBackground", "Card colour"),
      color("cardBorderColor", "Border colour"),
      color("selectedColor", "Highlight when selected"),
      px("cardRadius", "Corner radius", 0, 60),
      px("cardPadding", "Inner spacing", 0, 60),
      select("cardShadow", "Shadow", [["none", "None"], ["sm", "Small"], ["md", "Medium"], ["lg", "Large"]]),
    ],
  },
  productTitle: {
    title: "Product name",
    controls: [color("titleColor", "Colour"), px("titleSize", "Size", 8, 60), select("titleWeight", "Weight", [["400", "Regular"], ["500", "Medium"], ["600", "Semibold"], ["700", "Bold"], ["800", "Extra bold"]])],
  },
  productPrice: { title: "Price", controls: [color("priceColor", "Price colour"), px("priceSize", "Price size", 8, 80), color("savingColor", "Savings colour")] },
  galleryImage: { title: "Photos", controls: [RADIUS, SHADOW] },
  button: {
    title: "Button",
    controls: [
      select("buttonSize", "Size", [["sm", "Small"], ["md", "Medium"], ["lg", "Large"], ["xl", "Extra large"]]),
      { key: "fullWidth", label: "Full width", kind: "toggle" },
      color("backgroundColor", "Button colour"),
      color("color", "Text colour"),
      px("fontSize", "Text size", 8, 120),
      WEIGHTS,
      RADIUS,
      SHADOW,
    ],
  },
  icon: { title: "Icon", controls: [px("fontSize", "Size", 8, 120), color("color", "Colour"), color("backgroundColor", "Background"), RADIUS, px("paddingTop", "Padding", 0, 400)] },
  divider: { title: "Line", controls: [color("color", "Colour"), px("height", "Thickness", 1, 20), { key: "widthPercent", label: "Width", kind: "number", min: 5, max: 100, step: 1, unit: "%" }] },
  badge: { title: "Badge", controls: [color("backgroundColor", "Background"), color("color", "Text colour"), px("fontSize", "Text size", 8, 120), RADIUS] },
};

export default function StylePanel({ node, groups }) {
  const { state, actions } = useEditor();
  const device = state.device;
  const effective = resolveStyle(node.style, device);
  const own = node.style?.[device] ?? {};
  const DeviceIcon = DEVICE_ICON[device];

  if (!groups?.length) {
    return <p className="m-0 p-4 text-[13px] text-ink-tertiary">This block has no style settings.</p>;
  }

  return (
    <div className="flex flex-col">
      <div className="flex items-center gap-2 border-b border-hairline bg-[#f4f7fd] px-4 py-2 text-[12px] text-ink-secondary" data-testid="style-device">
        <DeviceIcon size={14} aria-hidden="true" />
        Editing <strong className="font-semibold text-ink">{DEVICE_NAME[device]}</strong>
        {device !== "desktop" ? <span className="text-ink-tertiary">— unset values follow larger screens</span> : null}
      </div>
      {groups.map((name) => {
        const group = STYLE_GROUPS[name];
        if (!group) return null;
        return (
          <details key={name} open className="group border-b border-hairline">
            <summary className="cursor-pointer list-none px-4 py-2.5 text-[12px] font-semibold uppercase tracking-wide text-ink-secondary [&::-webkit-details-marker]:hidden">
              {group.title}
            </summary>
            <div className="grid grid-cols-2 gap-3 px-4 pb-4">
              {group.controls.map((control) => {
                const source = valueSource(node.style, device, control.key);
                const inherited = source && source !== device;
                const wide = control.kind === "color" || control.kind === "select" || control.kind === "toggle";
                const set = (value) => actions.setStyle(node.id, control.key, value, control.kind === "number");
                return (
                  <div key={control.key} className={cx("relative", wide && "col-span-2")}>
                    <StyleControl control={control} value={own[control.key]} inheritedValue={inherited ? effective[control.key] : undefined} onChange={set} />
                    {own[control.key] !== undefined && control.kind !== "color" ? (
                      <button
                        type="button"
                        className="absolute right-0 top-0 inline-flex items-center gap-0.5 text-[10px] text-[#1f6feb] hover:underline"
                        onClick={() => set(undefined)}
                        title={device === "desktop" ? "Back to the block default" : "Back to the larger screen's value"}
                      >
                        <RotateCcw size={10} aria-hidden="true" /> reset
                      </button>
                    ) : null}
                    {inherited ? (
                      <p className="m-0 mt-0.5 text-[10px] text-ink-tertiary">From {DEVICE_NAME[source].toLowerCase()}</p>
                    ) : null}
                  </div>
                );
              })}
            </div>
          </details>
        );
      })}
    </div>
  );
}

function StyleControl({ control, value, inheritedValue, onChange }) {
  const testId = `style-${control.key}`;
  switch (control.kind) {
    case "color":
      return <ColorField label={control.label} value={value} onChange={onChange} placeholder={inheritedValue ?? "Default"} testId={testId} />;
    case "select":
      return (
        <SelectField
          label={control.label}
          value={value ?? ""}
          options={inheritedValue !== undefined ? control.options.map(([v, l]) => [v, v === "" ? `Inherited (${labelOf(control, inheritedValue)})` : l]) : control.options}
          onChange={(next) => onChange(next === "" ? undefined : next)}
          testId={testId}
        />
      );
    case "toggle":
      return <ToggleField label={control.label} value={value ?? inheritedValue ?? false} onChange={onChange} testId={testId} />;
    default:
      return (
        <NumberField
          label={control.label}
          value={value}
          min={control.min}
          max={control.max}
          step={control.step}
          unit={control.unit}
          placeholder={inheritedValue !== undefined ? String(inheritedValue) : "Auto"}
          onChange={onChange}
          testId={testId}
        />
      );
  }
}

function labelOf(control, value) {
  return control.options.find(([v]) => v === String(value))?.[1] ?? String(value);
}
