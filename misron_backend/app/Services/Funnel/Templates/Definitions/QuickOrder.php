<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * The shortest path to an order, for visitors who arrive from an advert on a
 * phone: photos and price at the top, the form right below, almost nothing else.
 */
class QuickOrder extends Template
{
    public function key(): string
    {
        return 'quick-order';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Quick order (mobile ads)' : 'কুইক অর্ডার (মোবাইল বিজ্ঞাপন)';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'Photos, price and the order form on one short screen. Made for visitors arriving from a social media advert on a phone.'
            : 'ছবি, দাম আর অর্ডার ফর্ম একই ছোট পেজে। সোশ্যাল মিডিয়া বিজ্ঞাপন থেকে মোবাইলে আসা ক্রেতার জন্য।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['Short', 'Mobile first'] : ['ছোট পেজ', 'মোবাইল ফার্স্ট'];
    }

    public function build(Builder $b): array
    {
        return [
            'settings' => $b->settings(['accentColor' => '#e11d48', 'backgroundColor' => '#ffffff', 'maxWidth' => 760]),
            'sections' => [
                $b->section([
                    $b->node('product_gallery', ['offerKey' => 'offer', 'layout' => 'carousel']),
                    $b->node('product_card', ['layout' => 'horizontal', 'showDescription' => false, 'showStock' => false, 'ctaText' => $b->t('সিলেক্ট করুন', 'Select'), 'ctaAction' => 'select_and_scroll'], [], null, ['productBindings' => [
                        $b->binding('offer', $b->t('আপনার পণ্য', 'Your product'), ['type' => 'none'], ['defaultSelected' => true]),
                    ]]),
                    $b->node('product_variations', ['offerKey' => 'offer', 'display' => 'buttons']),
                    $b->node('quantity_selector', ['offerKey' => 'offer', 'max' => 10]),
                    $b->usp([
                        ['truck', $b->t('দ্রুত ডেলিভারি', 'Fast delivery')],
                        ['wallet', $b->t('সহজ পেমেন্ট', 'Easy payment')],
                    ]),
                ], ['desktop' => ['paddingTop' => 20, 'paddingBottom' => 16, 'gap' => 12], 'mobile' => ['paddingTop' => 12, 'paddingBottom' => 12]]),
                $b->checkoutSection(null, ['desktop' => ['backgroundColor' => '#fff1f3', 'paddingTop' => 24, 'paddingBottom' => 32], 'mobile' => ['paddingTop' => 16, 'paddingBottom' => 24]], $b->t('অর্ডার করতে তথ্য দিন', 'Enter your details to order')),
                $b->sticky($b->t('অর্ডার করুন', 'Order now'), null, 'offer'),
            ],
            'slots' => [
                $this->slot($b, 'main', 'আপনার পণ্য', 'Your product', ['offer' => 1], true),
            ],
            'todos' => [],
        ];
    }
}
