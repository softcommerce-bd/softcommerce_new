"use client";

/**
 * Size guide — the measurement table, rendered inline on the product panel.
 *
 * The chart is authored per category in the admin and arrives on the product
 * payload as `size_chart`, already resolved (the API walks up the category
 * tree, so a product on a deep child still gets its ancestor's chart). When no
 * ancestor has a chart the API sends null and this renders nothing, so the
 * panel closes up as if the table were never there.
 *
 * It is always visible rather than behind a trigger: a shopper choosing a size
 * needs the numbers at the moment they look at the chips, and a table this
 * small does not earn an interaction to reveal it.
 *
 * Rows are shown exactly as the admin entered them rather than being filtered
 * against the product's variant chips: chart sizes (M, L, XL) and variant
 * labels (36, 38, 40) are authored independently and often do not share a
 * vocabulary, so filtering would empty the table instead of narrowing it.
 * When they do match, the row for the selected size is highlighted.
 *
 * The unit switch appears only when the chart actually carries both units;
 * most charts are inches-only, and a dead CM tab is worse than no tab.
 */

import { useState } from "react";

export default function SizeGuide({ sizeChart, selectedVariant, compact = false }) {
  const [unit, setUnit] = useState("inch");

  // Guard on rows as well as on the object: a chart whose details were never
  // filled in would otherwise render a header with an empty body.
  if (!sizeChart?.rows?.length || !sizeChart?.columns?.length) return null;

  const bothUnits = sizeChart.has_inch && sizeChart.has_cen;
  // With one unit there is nothing to switch, so the active unit is whichever
  // one the chart carries rather than whatever the toggle last held.
  const activeUnit = bothUnits ? unit : sizeChart.has_cen ? "cen" : "inch";

  const meta = [
    sizeChart.fit_type && { label: "Fit", value: sizeChart.fit_type },
    sizeChart.stretch_type && { label: "Stretch", value: sizeChart.stretch_type },
  ].filter(Boolean);

  // Sizes are compared case-insensitively and trimmed so "  m" from the admin
  // still lines up with an "M" chip.
  const normalise = (value) => String(value ?? "").trim().toLowerCase();
  const selected = normalise(selectedVariant);

  const cellPad = compact ? "px-2.5 py-2" : "px-3 py-2.5";

  return (
    <section
      className={compact ? "" : "border-b border-hairline py-6"}
      aria-label="Size guide"
    >
      <div className="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
        <p className="type-eyebrow text-ink-tertiary">Size guide</p>

        {bothUnits ? (
          <div
            className="inline-flex rounded-full border border-hairline p-0.5"
            role="group"
            aria-label="Measurement unit"
          >
            {[
              { key: "inch", label: "in" },
              { key: "cen", label: "cm" },
            ].map((option) => (
              <button
                key={option.key}
                type="button"
                onClick={() => setUnit(option.key)}
                aria-pressed={activeUnit === option.key}
                className={`type-caption rounded-full px-3 py-1 transition-colors ${
                  activeUnit === option.key
                    ? "bg-ink text-surface"
                    : "text-ink-secondary hover:text-ink"
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>
        ) : (
          <span className="type-caption text-ink-quaternary">
            {activeUnit === "cen" ? "in centimetres" : "in inches"}
          </span>
        )}
      </div>

      {/* The table scrolls sideways on its own rather than widening the
          panel: charts with many measurement points are common. */}
      <div className="scrollbar-subtle overflow-x-auto rounded-lg border border-hairline">
        <table className="w-full min-w-max border-collapse">
          <thead>
            <tr className="bg-surface-hover">
              <th
                scope="col"
                className={`type-caption whitespace-nowrap text-left font-medium uppercase tracking-wide text-ink-tertiary ${cellPad}`}
              >
                Size
              </th>
              {sizeChart.columns.map((column) => (
                <th
                  key={column.id}
                  scope="col"
                  className={`type-caption whitespace-nowrap text-left font-medium uppercase tracking-wide text-ink-tertiary ${cellPad}`}
                >
                  {column.name}
                </th>
              ))}
            </tr>
          </thead>

          <tbody>
            {sizeChart.rows.map((row) => {
              const isSelected = selected && normalise(row.size) === selected;

              return (
                <tr
                  key={row.size}
                  aria-current={isSelected ? "true" : undefined}
                  className={`border-t border-hairline ${
                    isSelected ? "bg-ink/4" : ""
                  }`}
                >
                  <th
                    scope="row"
                    className={`type-footnote whitespace-nowrap text-left font-medium text-ink ${cellPad}`}
                  >
                    {row.size}
                  </th>
                  {sizeChart.columns.map((column, index) => (
                    <td
                      key={column.id}
                      className={`type-footnote tabular whitespace-nowrap text-ink-secondary ${cellPad}`}
                    >
                      {/* An em dash keeps the column aligned where the admin
                          left a cell blank. */}
                      {row[activeUnit]?.[index] || "—"}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Fit and stretch describe the garment; they are not choices to make,
          so they read as a quiet line under the table rather than as chips. */}
      {meta.length > 0 && (
        <dl className="mt-2.5 flex flex-wrap gap-x-5 gap-y-1">
          {meta.map((item) => (
            <div key={item.label} className="flex items-baseline gap-1.5">
              <dt className="type-caption text-ink-quaternary">{item.label}</dt>
              <dd className="type-caption text-ink-secondary">{item.value}</dd>
            </div>
          ))}
        </dl>
      )}

      {!compact && sizeChart.description && (
        <p className="type-caption mt-2.5 whitespace-pre-line text-ink-tertiary">
          {sizeChart.description}
        </p>
      )}

      {!compact && sizeChart.photos?.length > 0 && (
        <div className="mt-3 grid grid-cols-3 gap-2">
          {sizeChart.photos.map((photo) => (
            <img
              key={photo}
              src={photo}
              alt={`${sizeChart.name} measurement reference`}
              loading="lazy"
              className="w-full rounded-lg border border-hairline object-cover"
            />
          ))}
        </div>
      )}
    </section>
  );
}
