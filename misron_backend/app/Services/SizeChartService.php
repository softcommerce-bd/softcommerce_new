<?php

namespace App\Services;

use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\MeasurementPoint;
use App\Models\SizeChart;

/**
 * Resolves the size chart a storefront product should display.
 *
 * Charts are authored against one category (admin > Size Charts), but products
 * usually sit on a deeper child category than the one the chart was attached
 * to. So resolution walks up the `parent_id` chain from the product's own
 * category and returns the first chart it meets — the nearest ancestor wins,
 * which lets a specific child override a broad parent's chart.
 */
class SizeChartService
{
    /**
     * How far up the category tree to walk. The tree is only a few levels
     * deep; the cap is a guard against a `parent_id` cycle in the data.
     */
    private const MAX_DEPTH = 10;

    /**
     * Find the chart for a category id, walking up to its ancestors.
     *
     * @param  int|null  $categoryId
     * @return \App\Models\SizeChart|null
     */
    public function resolveForCategory($categoryId)
    {
        if (empty($categoryId)) {
            return null;
        }

        $seen = [];
        $depth = 0;

        while ($categoryId && $depth < self::MAX_DEPTH) {
            // A malformed tree that points back at itself would otherwise spin
            // until the depth cap; stopping on a repeat is cheaper and clearer.
            if (in_array($categoryId, $seen, true)) {
                return null;
            }
            $seen[] = $categoryId;

            $chart = SizeChart::where('category_id', $categoryId)->first();
            if ($chart) {
                return $chart;
            }

            $categoryId = Category::where('id', $categoryId)->value('parent_id');
            $depth++;
        }

        return null;
    }

    /**
     * Shape a chart for the storefront.
     *
     * The admin screen renders points as columns and sizes as rows, so the
     * payload is built row-first in that same orientation: the frontend can
     * render it as a table without transposing anything.
     *
     * Returns null when the chart has no usable rows, so the caller can simply
     * omit the size guide rather than render an empty table.
     *
     * @param  \App\Models\SizeChart|null  $chart
     * @return array|null
     */
    public function present($chart)
    {
        if (! $chart) {
            return null;
        }

        $measurementOptions = json_decode($chart->measurement_option, true) ?? [];
        $pointIds = json_decode($chart->measurement_points, true) ?? [];
        $sizeIds = json_decode($chart->size_options, true) ?? [];

        if (empty($pointIds) || empty($sizeIds)) {
            return null;
        }

        // Ordered by the admin's own selection order rather than by id, so the
        // storefront columns read left-to-right the same way the admin built
        // them (Polo Chest, Polo Length, Joggers Waist, …).
        $points = MeasurementPoint::whereIn('id', $pointIds)->get()->keyBy('id');
        $sizes = AttributeValue::whereIn('id', $sizeIds)->get()->keyBy('id');

        // Flat detail rows become a [point][size] lookup, matching how the
        // admin show/edit screens index them.
        $values = [];
        foreach ($chart->sizeChartDetails as $detail) {
            $values['inch'][$detail->measurement_point_id][$detail->attribute_value_id] = $detail->inch_value;
            $values['cen'][$detail->measurement_point_id][$detail->attribute_value_id] = $detail->cen_value;
        }

        $columns = [];
        foreach ($pointIds as $pointId) {
            if (isset($points[$pointId])) {
                $columns[] = [
                    'id' => (int) $pointId,
                    'name' => $points[$pointId]->name,
                ];
            }
        }

        $rows = [];
        foreach ($sizeIds as $sizeId) {
            if (! isset($sizes[$sizeId])) {
                continue;
            }

            $inch = [];
            $cen = [];
            foreach ($columns as $column) {
                $inch[] = $values['inch'][$column['id']][$sizeId] ?? null;
                $cen[] = $values['cen'][$column['id']][$sizeId] ?? null;
            }

            $rows[] = [
                'size' => $sizes[$sizeId]->value,
                'inch' => $inch,
                'cen' => $cen,
            ];
        }

        if (empty($columns) || empty($rows)) {
            return null;
        }

        return [
            'id' => (int) $chart->id,
            'name' => $chart->name,
            'fit_type' => $this->label($chart->fit_type),
            'stretch_type' => $this->label($chart->stretch_type),
            'description' => $chart->description,
            // get_images_path explodes on ',' unconditionally, so a null
            // `photos` would yield one bogus entry rather than an empty list.
            'photos' => empty($chart->photos) ? [] : array_values(array_filter(get_images_path($chart->photos))),
            // The frontend shows a unit toggle only when both units carry
            // values, so these flags decide that on the server.
            'has_inch' => in_array('inch', $measurementOptions, true),
            'has_cen' => in_array('cen', $measurementOptions, true),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * Convenience wrapper: category id straight to presented payload.
     *
     * @param  int|null  $categoryId
     * @return array|null
     */
    public function forCategory($categoryId)
    {
        return $this->present($this->resolveForCategory($categoryId));
    }

    /**
     * Turn a stored slug ("slim_fit") into a display label ("Slim Fit").
     *
     * @param  string|null  $value
     * @return string|null
     */
    private function label($value)
    {
        if (empty($value)) {
            return null;
        }

        return ucwords(str_replace('_', ' ', $value));
    }
}
