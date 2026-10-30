<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * Sells more pieces per order: a centred headline, the photos, and bundle
 * cards where buying more is clearly cheaper per piece.
 */
class BundleSaver extends Template
{
    public function key(): string
    {
        return 'bundle-saver';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Bundle and save' : 'বান্ডেল অফার — বেশি নিলে সাশ্রয়';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'Centred headline, photo carousel and bundle cards that show the price per piece. Built to raise the number of pieces in each order.'
            : 'মাঝখানে হেডলাইন, ছবির স্লাইডার আর বান্ডেল কার্ড — প্রতি পিসের দাম দেখিয়ে বেশি কিনতে উৎসাহ দেয়।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['Bundles', 'Price per piece'] : ['বান্ডেল', 'পিস প্রতি দাম'];
    }

    public function build(Builder $b): array
    {
        $bundles = $b->node('bundle_offer_selector', [
            'heading' => $b->t('কতগুলো নেবেন?', 'How many do you need?'),
            'layout' => 'stack',
            'showSavings' => true,
            'showPerUnit' => true,
        ], ['desktop' => ['maxWidth' => 720]], null, ['productBindings' => [
            $b->binding('bundle_1', $b->t('১টি', 'Single'), ['type' => 'none']),
            $b->binding('bundle_2', $b->t('২টি একসাথে', 'Two together'), ['type' => 'percent', 'value' => 10], ['badge' => $b->t('জনপ্রিয়', 'Popular')]),
            $b->binding('bundle_4', $b->t('৪টি একসাথে', 'Four together'), ['type' => 'percent', 'value' => 20], ['badge' => $b->t('সবচেয়ে সাশ্রয়ী', 'Biggest saving'), 'featured' => true]),
        ]]);

        return [
            'settings' => $b->settings(['accentColor' => '#0b7a75', 'backgroundColor' => '#ffffff']),
            'sections' => [
                $b->announcement($b->t('যত বেশি নেবেন, তত কম পড়বে পিস প্রতি দাম', 'The more you buy, the less each piece costs'), 'percent'),
                $b->section([
                    $b->badge($b->t('বান্ডেল অফার', 'Bundle offer')),
                    $b->heading($b->t('একসাথে নিলে পিস প্রতি কম দাম', 'Buy together, pay less per piece'), 'h1', ['desktop' => ['textAlign' => 'center', 'fontSize' => 38, 'marginTop' => 10], 'mobile' => ['fontSize' => 27, 'textAlign' => 'center']]),
                    $b->text($b->t('নিচের কার্ড থেকে আপনার সংখ্যা বেছে নিন। দাম সাথে সাথে হিসাব হয়ে যাবে।', 'Pick your quantity below. The price is worked out for you at once.'), ['desktop' => ['textAlign' => 'center', 'maxWidth' => 640, 'marginBottom' => 16]]),
                    $b->node('product_gallery', ['offerKey' => 'bundle_1', 'layout' => 'carousel'], ['desktop' => ['maxWidth' => 520]]),
                ], ['desktop' => ['backgroundColor' => '#eefaf9', 'paddingTop' => 48, 'paddingBottom' => 40, 'alignItems' => 'center'], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->section([$bundles], ['desktop' => ['paddingTop' => 40, 'paddingBottom' => 40], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->section([$b->usp([
                    ['truck', $b->t('দ্রুত ডেলিভারি', 'Fast delivery')],
                    ['wallet', $b->t('সহজ পেমেন্ট', 'Easy payment')],
                    ['badge-check', $b->t('আসল পণ্য', 'Genuine product')],
                ])], ['desktop' => ['paddingTop' => 8, 'paddingBottom' => 32]]),
                $b->section([$b->orderFaq()], ['desktop' => ['backgroundColor' => '#f7f7f8', 'paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->checkoutSection($b->t('আপনার অর্ডার সম্পূর্ণ করুন', 'Complete your order')),
                $b->sticky($b->t('অর্ডার করুন', 'Order now'), $b->t('বেশি নিলে বেশি সাশ্রয়', 'Buy more, save more')),
            ],
            'slots' => [
                $this->slot($b, 'main', 'আপনার পণ্য', 'Your product', ['bundle_1' => 1, 'bundle_2' => 2, 'bundle_4' => 4], true, 'একই পণ্য ১টি, ২টি ও ৪টির বান্ডেলে দেখানো হবে।', 'The same product is offered as 1, 2 and 4 pieces.'),
            ],
            'todos' => [
                $b->t('বান্ডেলের ছাড় (১০% ও ২০%) নমুনা মাত্র — নিজের ছাড় ঠিক করুন।', 'The bundle discounts (10% and 20%) are examples. Set your own.'),
            ],
        ];
    }
}
