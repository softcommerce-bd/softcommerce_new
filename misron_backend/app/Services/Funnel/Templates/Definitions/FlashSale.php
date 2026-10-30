<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * A time-boxed sale. The countdown and the offer end at the same real moment
 * (a week from the day the page is created, easy to change); when it passes
 * the offer pauses by itself, so the page never promises a price it will not honour.
 */
class FlashSale extends Template
{
    public function key(): string
    {
        return 'flash-sale';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Flash sale' : 'ফ্ল্যাশ সেল';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'A bold sale page with a real countdown, a big discount badge and one price. The offer pauses itself when the time is up.'
            : 'আসল কাউন্টডাউন, বড় ছাড়ের ব্যাজ আর একটি দাম। সময় শেষ হলে অফার নিজে থেকেই বন্ধ হয়ে যায়।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['Countdown', 'Discount'] : ['কাউন্টডাউন', 'ছাড়'];
    }

    public function build(Builder $b): array
    {
        $end = Builder::END_TOKEN;

        $hero = [
            $b->node('discount_badge', ['offerKey' => 'sale', 'shape' => 'pill']),
            $b->heading($b->t('শুধু আজকের জন্য বিশেষ দাম', 'A special price for a short time'), 'h1', ['desktop' => ['fontSize' => 40, 'marginTop' => 10], 'mobile' => ['fontSize' => 28]]),
            $b->node('product_price', ['offerKey' => 'sale', 'showOriginal' => true, 'showSavings' => true], ['desktop' => ['priceSize' => 38]]),
            $b->button($b->t('অফারটি নিন', 'Get this offer'), 'checkout', ['desktop' => ['buttonSize' => 'xl'], 'mobile' => ['buttonSize' => 'lg', 'fullWidth' => true]]),
        ];

        return [
            'settings' => $b->settings(['accentColor' => '#d6291f', 'backgroundColor' => '#ffffff']),
            'sections' => [
                $b->announcement($b->t('ফ্ল্যাশ সেল চলছে — সময় শেষ হওয়ার আগেই অর্ডার করুন', 'Flash sale on — order before the time runs out'), 'flame'),
                $b->section([
                    $b->countdown($b->t('অফার শেষ হতে বাকি', 'Offer ends in'), 'sale', ['desktop' => ['textAlign' => 'center']]),
                ], ['desktop' => ['backgroundColor' => '#fff1ef', 'paddingTop' => 20, 'paddingBottom' => 20], 'mobile' => ['paddingTop' => 14, 'paddingBottom' => 14]]),
                $this->heroSplit($b, $hero, 'sale', ['desktop' => ['paddingTop' => 40, 'paddingBottom' => 48], 'mobile' => ['paddingTop' => 20, 'paddingBottom' => 28]]),
                $b->section([$b->node('product_offer_selector', [
                    'heading' => $b->t('এই অফারে আপনি পাচ্ছেন', 'What you get in this offer'),
                    'selectionMode' => 'single',
                    'cardLayout' => 'horizontal',
                    'showOriginalPrice' => true,
                    'showSavings' => true,
                ], ['desktop' => ['maxWidth' => 640]], null, ['productBindings' => [
                    $b->binding('sale', $b->t('ফ্ল্যাশ সেল অফার', 'Flash sale offer'), ['type' => 'percent', 'value' => 20, 'endsAt' => $end], ['badge' => $b->t('সীমিত সময়', 'Limited time'), 'defaultSelected' => true]),
                ]])], ['desktop' => ['backgroundColor' => '#f7f7f8', 'paddingTop' => 40, 'paddingBottom' => 40], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->section([$b->usp([
                    ['truck', $b->t('দ্রুত ডেলিভারি', 'Fast delivery')],
                    ['wallet', $b->t('সহজ পেমেন্ট', 'Easy payment')],
                    ['badge-check', $b->t('আসল পণ্য', 'Genuine product')],
                ])], ['desktop' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->checkoutSection($b->t('অফার শেষ হওয়ার আগে অর্ডার করুন', 'Order before the offer ends')),
                $b->sticky($b->t('অফারটি নিন', 'Get the offer'), null, 'sale'),
            ],
            'slots' => [
                $this->slot($b, 'main', 'সেলের পণ্য', 'Sale product', ['sale' => 1], true),
            ],
            'todos' => [
                $b->t('অফার শেষের সময় এখন থেকে ৭ দিন পরে বসানো আছে। কাউন্টডাউন ও অফার দুটোর সময়ই নিজের সময় অনুযায়ী বদলান।', 'The end time is set to seven days from today. Change it on both the countdown and the offer.'),
                $b->t('২০% ছাড় নমুনা মাত্র — নিজের ছাড় ঠিক করুন।', 'The 20% discount is an example. Set your own.'),
            ],
        ];
    }
}
