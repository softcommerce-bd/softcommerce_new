<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\Upload;

/**
 * Search and social-sharing details of a funnel page.
 *
 * Title, description, share picture and the noindex switch are kept on the
 * funnel record, not in the versioned document, so they can be corrected at
 * any time without publishing again. The page gets them from here, in the one
 * shape the storefront turns into <head> tags.
 */
class FunnelSeo
{
    /** Keys kept in funnels.settings.seo. */
    public const SETTINGS = ['noindex', 'og_title', 'og_description', 'canonical_url'];

    /**
     * @param array|null $offers resolved offers (OfferEngine::resolve), for structured data; null skips it
     */
    public function forFunnel(Funnel $funnel, ?array $document, ?array $offers = null): array
    {
        $seo = $this->settings($funnel);
        $title = $funnel->meta_title ?: $funnel->title;
        $description = $funnel->meta_description ?: ($funnel->description ?: null);
        $canonical = $seo['canonical_url'] ?: $funnel->publicUrl();
        $image = $this->shareImage($funnel);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'noindex' => (bool) $seo['noindex'],
            'language' => ($document['page']['settings']['language'] ?? 'bn') === 'en' ? 'en' : 'bn',
            'og' => [
                'title' => $seo['og_title'] ?: $title,
                'description' => $seo['og_description'] ?: $description,
                'image' => $image,
                'url' => $canonical,
                'type' => 'website',
            ],
            'json_ld' => $offers === null ? null : $this->structuredData($funnel, $title, $description, $canonical, $image, $offers),
        ];
    }

    /** The seo block of the funnel settings, with every key present. */
    public function settings(Funnel $funnel): array
    {
        $stored = is_array($funnel->settings) ? ($funnel->settings['seo'] ?? []) : [];

        return [
            'noindex' => (bool) ($stored['noindex'] ?? false),
            'og_title' => $stored['og_title'] ?? null,
            'og_description' => $stored['og_description'] ?? null,
            'canonical_url' => $stored['canonical_url'] ?? null,
        ];
    }

    /**
     * Applies edits to the seo block. Returns the new value for funnels.settings,
     * keeping any other setting. Text is trimmed; empty means "use the default".
     */
    public function merge(Funnel $funnel, array $input): array
    {
        $settings = is_array($funnel->settings) ? $funnel->settings : [];
        $seo = $this->settings($funnel);

        if (array_key_exists('noindex', $input)) {
            $seo['noindex'] = $input['noindex'] === true;
        }
        foreach (['og_title' => 255, 'og_description' => 500] as $field => $max) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field] === null ? '' : trim((string) $input[$field]);
                if (mb_strlen($value) > $max) {
                    throw new FunnelException("The {$field} must be at most {$max} characters.", 'invalid_' . $field, 422);
                }
                $seo[$field] = $value === '' ? null : $value;
            }
        }
        if (array_key_exists('canonical_url', $input)) {
            $value = $input['canonical_url'] === null ? '' : trim((string) $input['canonical_url']);
            if ($value !== '' && (strlen($value) > 500 || !preg_match('#^https://[^\s/]+(/\S*)?$#', $value))) {
                throw new FunnelException('The canonical address must be a full https:// address.', 'invalid_canonical_url', 422);
            }
            $seo['canonical_url'] = $value === '' ? null : $value;
        }

        $settings['seo'] = $seo;

        return $settings;
    }

    /** The share picture: the chosen one, or nothing (never the placeholder image). */
    private function shareImage(Funnel $funnel): ?string
    {
        if (!$funnel->og_image_upload_id) {
            return null;
        }
        $upload = Upload::find($funnel->og_image_upload_id);
        if (!$upload) {
            return null;
        }

        return $upload->external_link ?: my_asset($upload->file_name);
    }

    /**
     * schema.org Product for the first offer that sells one piece of one
     * product and can be ordered now. Read from the live offer data, so the
     * price and availability are the shop's current ones.
     */
    private function structuredData(Funnel $funnel, string $title, ?string $description, string $canonical, ?string $image, array $offers): ?array
    {
        foreach ($offers as $offer) {
            $items = $offer['items'] ?? [];
            if (count($items) !== 1 || ($items[0]['quantity'] ?? 1) !== 1 || empty($items[0]['name']) || empty($offer['price'])) {
                continue;
            }
            $item = $items[0];

            try {
                $currency = get_system_default_currency()->code;
            } catch (\Throwable $e) {
                $currency = 'BDT';
            }

            $data = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $item['name'],
                'url' => $canonical,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format((float) $offer['price']['offer']['amount'], 2, '.', ''),
                    'priceCurrency' => $currency,
                    'availability' => !empty($offer['available']) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $canonical,
                ],
            ];
            $picture = $item['image'] ?? $image;
            if ($picture) {
                $data['image'] = $picture;
            }
            if ($description) {
                $data['description'] = $description;
            }

            return $data;
        }

        return null;
    }
}
