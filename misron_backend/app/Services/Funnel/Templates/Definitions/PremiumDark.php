<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * A darker, calmer page with serif headlines for products sold on look and
 * feel: jewellery, watches, gifts, home decor.
 */
class PremiumDark extends Template
{
    public function key(): string
    {
        return 'premium-dark';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Premium dark' : 'প্রিমিয়াম ডার্ক';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'Dark background, gold accents and serif headlines. For products sold on style: jewellery, watches, gifts, decor.'
            : 'গাঢ় ব্যাকগ্রাউন্ড, সোনালি অ্যাকসেন্ট আর সেরিফ হেডলাইন। স্টাইল নির্ভর পণ্য — গয়না, ঘড়ি, উপহার, সাজসজ্জা।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['Dark', 'Elegant'] : ['ডার্ক', 'মার্জিত'];
    }

    public function build(Builder $b): array
    {
        $dark = ['backgroundColor' => '#14110f', 'color' => '#f5efe6'];
        // Cards inside blocks (benefits, questions) are light, so their sections are light too.
        $darker = ['backgroundColor' => '#f3ece0', 'color' => '#1d1916'];

        return [
            'settings' => $b->settings(['accentColor' => '#c9a24a', 'backgroundColor' => '#14110f', 'textColor' => '#f5efe6', 'fontFamily' => 'serif']),
            'sections' => [
                $b->announcement($b->t('সীমিত সংগ্রহ', 'Limited collection'), 'gem'),
                $this->heroSplit($b, [
                    $b->badge($b->t('নতুন সংগ্রহ', 'New collection'), 'outline'),
                    $b->heading($b->t('যে সৌন্দর্য সময়কে হার মানায়', 'Beauty that outlasts time'), 'h1', ['desktop' => ['fontSize' => 44, 'marginTop' => 10], 'mobile' => ['fontSize' => 30]]),
                    $b->text($b->t('যত্ন নিয়ে বাছাই করা এই পণ্যটি আপনার নিজের জন্য, কিংবা প্রিয়জনের জন্য উপহার।', 'Chosen with care, for yourself or as a gift for someone you love.')),
                    $b->node('product_price', ['offerKey' => 'offer', 'showOriginal' => true, 'showSavings' => true], ['desktop' => ['priceSize' => 34]]),
                    $b->button($b->t('অর্ডার করুন', 'Order now'), 'checkout', ['desktop' => ['buttonSize' => 'xl'], 'mobile' => ['fullWidth' => true]]),
                ], 'offer', ['desktop' => $dark + ['paddingTop' => 64, 'paddingBottom' => 64], 'mobile' => $dark + ['paddingTop' => 24, 'paddingBottom' => 32]]),
                $b->section([$b->benefits($b->t('কেন এটি বিশেষ', 'What makes it special'), [
                    ['gem', $b->t('নিখুঁত কারুকাজ', 'Fine craft'), $b->t('প্রতিটি খুঁটিনাটি যত্ন নিয়ে তৈরি।', 'Every detail made with care.')],
                    ['gift', $b->t('উপহারের জন্য সেরা', 'Made for gifting'), $b->t('প্রিয়জনকে চমকে দেওয়ার মতো।', 'A gift worth giving.')],
                    ['sparkles', $b->t('দীর্ঘস্থায়ী', 'Built to last'), $b->t('বছরের পর বছর একই উজ্জ্বলতা।', 'Looks as good years from now.')],
                ])], ['desktop' => $darker + ['paddingTop' => 52, 'paddingBottom' => 52], 'mobile' => $darker + ['paddingTop' => 32, 'paddingBottom' => 32]]),
                $b->section([$b->node('product_card', ['layout' => 'horizontal', 'showDescription' => true, 'ctaText' => $b->t('সিলেক্ট করুন', 'Select'), 'ctaAction' => 'select_and_scroll'], ['desktop' => ['maxWidth' => 760, 'cardBackground' => '#1d1916', 'cardBorderColor' => '#3a322b', 'titleColor' => '#f5efe6', 'priceColor' => '#c9a24a']], null, ['productBindings' => [
                    $b->binding('offer', $b->t('এই পণ্যটি', 'This piece'), ['type' => 'none'], ['defaultSelected' => true]),
                ]])], ['desktop' => $dark + ['paddingTop' => 44, 'paddingBottom' => 44], 'mobile' => $dark + ['paddingTop' => 28, 'paddingBottom' => 28]]),
                $b->section([$b->orderFaq()], ['desktop' => $darker + ['paddingTop' => 44, 'paddingBottom' => 44]]),
                $b->checkoutSection($b->t('অর্ডার সম্পূর্ণ করুন', 'Complete your order'), $dark),
                $b->sticky($b->t('অর্ডার করুন', 'Order now'), null, 'offer'),
            ],
            'slots' => [
                $this->slot($b, 'main', 'আপনার পণ্য', 'Your product', ['offer' => 1], true),
            ],
            'todos' => [],
        ];
    }
}
