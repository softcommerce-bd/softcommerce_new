<?php

namespace App\Services\Funnel\Templates;

/**
 * One reusable page design. A definition is code, so it can be reviewed,
 * tested and shipped with the application; TemplateLibrary copies each one,
 * per language, into the funnel_templates table.
 *
 * build() returns:
 *   settings  page-wide settings (colours, font, language)
 *   sections  the page's top-level blocks
 *   slots     what the merchant must choose: each slot names the offers it
 *             fills and how many pieces each offer sells, e.g.
 *             ['key' => 'main', 'label' => '…', 'bindings' => ['offer_1' => 1, 'offer_2' => 2]]
 *   todos     short reminders shown in the editor's setup checklist
 *
 * No product is ever stored in a template, only the places that need one.
 */
abstract class Template
{
    /** Stable identifier, also the slug base: "classic-single-product". */
    abstract public function key(): string;

    abstract public function name(string $lang): string;

    abstract public function description(string $lang): string;

    /** @return array{settings: array, sections: array, slots: array, todos: array} */
    abstract public function build(Builder $b): array;

    public function category(): string
    {
        return 'sales';
    }

    /** Short tags shown on the gallery card. */
    public function tags(string $lang): array
    {
        return [];
    }

    protected function slot(Builder $b, string $key, string $bn, string $en, array $bindings, bool $required = true, ?string $hintBn = null, ?string $hintEn = null): array
    {
        return [
            'key' => $key,
            'label' => $b->t($bn, $en),
            'hint' => $hintBn === null ? null : $b->t($hintBn, (string) $hintEn),
            'bindings' => $bindings,
            'required' => $required,
        ];
    }

    /**
     * Left: wording and button. Right: the chosen product's photos. On phones
     * the photos come first, then the wording.
     */
    protected function heroSplit(Builder $b, array $left, string $offerKey, array $style = [], bool $reverse = false): array
    {
        return $b->section([
            $b->columns([
                $left,
                [$b->node('product_gallery', ['offerKey' => $offerKey, 'layout' => 'thumbs_bottom'])],
            ], ['desktop' => ['gap' => 40, 'alignItems' => 'center'], 'mobile' => ['gap' => 20]], ['stackOn' => 'mobile', 'reverseOnStack' => !$reverse]),
        ], $style);
    }
}
