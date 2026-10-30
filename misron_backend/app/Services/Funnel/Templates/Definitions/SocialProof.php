<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * Leads with trust: ratings and customer words. The review cards ship with
 * marked placeholders that the page cannot be published with, because a made-up
 * review is worse than none.
 */
class SocialProof extends Template
{
    public function key(): string
    {
        return 'social-proof';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Customer proof' : 'গ্রাহকের আস্থা';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'Trust icons, customer review cards and a comparison, then the offer. For products where buyers hesitate. You add the real reviews.'
            : 'ট্রাস্ট আইকন, গ্রাহকের রিভিউ কার্ড আর তুলনা — তারপর অফার। যে পণ্যে ক্রেতা দ্বিধা করেন। আসল রিভিউ আপনাকেই বসাতে হবে।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['Reviews', 'Trust'] : ['রিভিউ', 'আস্থা'];
    }

    public function build(Builder $b): array
    {
        $review = function (int $n) use ($b): array {
            return [
                'name' => $b->t("[[গ্রাহকের নাম {$n}]]", "[[Customer name {$n}]]"),
                'role' => $b->t('[[এলাকা]]', '[[Area]]'),
                'text' => $b->t('[[এখানে আপনার একজন আসল গ্রাহকের মন্তব্য লিখুন। অনুমতি ছাড়া বা বানিয়ে কোনো রিভিউ ব্যবহার করবেন না।]]', '[[Write a real customer’s comment here. Never use a made-up review or one used without permission.]]'),
                'rating' => 5,
            ];
        };

        return [
            'settings' => $b->settings(['accentColor' => '#1c7c54', 'backgroundColor' => '#ffffff']),
            'sections' => [
                $b->announcement($b->t('গ্রাহকদের পছন্দের পণ্য', 'A product our customers like'), 'star'),
                $b->section([
                    $b->heading($b->t('কেন গ্রাহকরা এটি বেছে নেন', 'Why customers choose this'), 'h1', ['desktop' => ['textAlign' => 'center', 'fontSize' => 36], 'mobile' => ['fontSize' => 26, 'textAlign' => 'center']]),
                    $b->node('trust_icons', ['items' => [
                        ['icon' => 'shield-check', 'title' => $b->t('নিরাপদ অর্ডার', 'Safe ordering')],
                        ['icon' => 'truck', 'title' => $b->t('দ্রুত ডেলিভারি', 'Fast delivery')],
                        ['icon' => 'headset', 'title' => $b->t('সাপোর্ট', 'Support')],
                        ['icon' => 'badge-check', 'title' => $b->t('আসল পণ্য', 'Genuine product')],
                    ]]),
                ], ['desktop' => ['backgroundColor' => '#eef8f3', 'paddingTop' => 44, 'paddingBottom' => 36], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 20]]),
                $this->heroSplit($b, [
                    $b->heading($b->t('আপনার পণ্যটি এখানে', 'Your product'), 'h2'),
                    $b->node('product_price', ['offerKey' => 'offer', 'showOriginal' => true, 'showSavings' => true], ['desktop' => ['priceSize' => 32]]),
                    $b->node('product_variations', ['offerKey' => 'offer', 'display' => 'buttons']),
                    $b->node('quantity_selector', ['offerKey' => 'offer', 'max' => 10]),
                    $b->button($b->t('অর্ডার করুন', 'Order now'), 'checkout', ['desktop' => ['buttonSize' => 'xl'], 'mobile' => ['fullWidth' => true]]),
                ], 'offer', ['desktop' => ['paddingTop' => 40, 'paddingBottom' => 40], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->section([$b->node('testimonial_cards', [
                    'heading' => $b->t('গ্রাহকরা কী বলছেন', 'What customers say'),
                    'layout' => 'grid',
                    'items' => [$review(1), $review(2), $review(3)],
                ])], ['desktop' => ['backgroundColor' => '#f7f7f8', 'paddingTop' => 44, 'paddingBottom' => 44], 'mobile' => ['paddingTop' => 28, 'paddingBottom' => 28]]),
                $b->section([$b->node('product_card', ['layout' => 'horizontal', 'showDescription' => true, 'ctaText' => $b->t('সিলেক্ট করুন', 'Select'), 'ctaAction' => 'select_and_scroll'], ['desktop' => ['maxWidth' => 720]], null, ['productBindings' => [
                    $b->binding('offer', $b->t('এই পণ্যটি', 'This product'), ['type' => 'none'], ['defaultSelected' => true]),
                ]])], ['desktop' => ['paddingTop' => 36, 'paddingBottom' => 36]]),
                $b->section([$b->guarantee($b->t('আপনার আস্থাই আমাদের পুঁজি', 'Your trust matters to us'), $b->t('অর্ডারের পর আপনার তথ্য শুধু ডেলিভারির কাজেই ব্যবহার হয়।', 'After you order, your details are used only for delivery.'))], ['desktop' => ['paddingTop' => 24, 'paddingBottom' => 40]]),
                $b->section([$b->orderFaq()], ['desktop' => ['backgroundColor' => '#f7f7f8', 'paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->checkoutSection($b->t('অর্ডার করুন', 'Place your order')),
                $b->sticky($b->t('অর্ডার করুন', 'Order now'), null, 'offer'),
            ],
            'slots' => [
                $this->slot($b, 'main', 'আপনার পণ্য', 'Your product', ['offer' => 1], true),
            ],
            'todos' => [
                $b->t('তিনটি রিভিউ কার্ডে আসল গ্রাহকের নাম ও মন্তব্য বসান, অথবা রিভিউ সেকশনটি মুছে দিন। [[ ]] চিহ্নসহ লেখা থাকলে পেজ প্রকাশ করা যাবে না।', 'Replace the three review cards with real customers’ names and words, or delete the section. The page cannot be published while any [[ ]] text remains.'),
            ],
        ];
    }
}
