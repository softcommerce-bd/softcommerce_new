<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * The all-rounder: one product, three package sizes, the reasons to buy, the
 * answers to the usual questions and the order form. Modelled on the blender
 * landing page that started the module.
 */
class ClassicSingleProduct extends Template
{
    public function key(): string
    {
        return 'classic-single-product';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Classic single product' : 'ক্লাসিক সিঙ্গেল প্রোডাক্ট';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'One product with photos, three package sizes, benefits, FAQ and the order form. A safe starting point for almost any product.'
            : 'একটি পণ্য, তিনটি প্যাকেজ, সুবিধা, প্রশ্নোত্তর আর অর্ডার ফর্ম। প্রায় যেকোনো পণ্যের জন্য নিরাপদ শুরু।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['One product', 'Packages', 'FAQ'] : ['একটি পণ্য', 'প্যাকেজ', 'প্রশ্নোত্তর'];
    }

    public function build(Builder $b): array
    {
        $hero = [
            $b->badge($b->t('বিশেষ অফার', 'Special offer')),
            $b->heading($b->t('আজই অর্ডার করুন, সেরা দামে', 'Order today at a special price'), 'h1', ['desktop' => ['fontSize' => 40, 'marginTop' => 8], 'mobile' => ['fontSize' => 28]]),
            $b->text($b->t('পছন্দের প্যাকেজ বেছে নিন, ফর্ম পূরণ করুন — অর্ডার কনফার্ম হলেই আমরা পাঠিয়ে দেব।', 'Pick the package you like and fill in the form. We send your order as soon as it is confirmed.')),
            $b->node('product_price', ['offerKey' => 'offer_1', 'showOriginal' => true, 'showSavings' => true], ['desktop' => ['priceSize' => 34]]),
            $b->button($b->t('এখনই অর্ডার করুন', 'Order now'), 'checkout', ['desktop' => ['buttonSize' => 'xl'], 'mobile' => ['buttonSize' => 'lg', 'fullWidth' => true]]),
        ];

        $packages = $b->node('product_offer_selector', [
            'heading' => $b->t('আপনার প্যাকেজ বেছে নিন', 'Choose your package'),
            'selectionMode' => 'single',
            'cardLayout' => 'vertical',
            'showOriginalPrice' => true,
            'showSavings' => true,
        ], ['desktop' => ['columns' => 3, 'gap' => 16], 'mobile' => ['columns' => 1, 'gap' => 12]], null, ['productBindings' => [
            $b->binding('offer_1', $b->t('১টি', '1 piece'), ['type' => 'none'], ['defaultSelected' => true]),
            $b->binding('offer_2', $b->t('২টির প্যাকেজ', 'Pack of 2'), ['type' => 'percent', 'value' => 10]),
            $b->binding('offer_3', $b->t('৩টির প্যাকেজ', 'Pack of 3'), ['type' => 'percent', 'value' => 15], ['badge' => $b->t('সেরা মূল্য', 'Best value'), 'featured' => true]),
        ]]);

        return [
            'settings' => $b->settings(['accentColor' => '#e8590c', 'backgroundColor' => '#ffffff']),
            'sections' => [
                $b->announcement($b->t('অর্ডার করুন ঘরে বসেই — ফর্ম পূরণ করলেই হবে', 'Order from home — just fill in the form')),
                $this->heroSplit($b, $hero, 'offer_1', ['desktop' => ['backgroundColor' => '#fff7f0', 'paddingTop' => 56, 'paddingBottom' => 56], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 28]]),
                $b->section([$b->usp([
                    ['truck', $b->t('দ্রুত ডেলিভারি', 'Fast delivery')],
                    ['banknote', $b->t('সহজ পেমেন্ট', 'Easy payment')],
                    ['badge-check', $b->t('আসল পণ্য', 'Genuine product')],
                    ['headset', $b->t('সাপোর্ট', 'Support')],
                ])], ['desktop' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->section([$b->benefits($b->t('কেন এই পণ্যটি নেবেন?', 'Why choose this product?'), [
                    ['sparkles', $b->t('মানসম্মত', 'Quality made'), $b->t('যত্ন নিয়ে বাছাই করা পণ্য, যা দীর্ঘদিন ব্যবহার করা যায়।', 'Carefully chosen and made to last.')],
                    ['thumbs-up', $b->t('ব্যবহার সহজ', 'Easy to use'), $b->t('কোনো ঝামেলা ছাড়াই প্রতিদিনের কাজে ব্যবহার করুন।', 'Use it every day without any fuss.')],
                    ['tag', $b->t('ন্যায্য দাম', 'Fair price'), $b->t('বড় প্যাকেজে আরও বেশি সাশ্রয়।', 'Bigger packages save you more.')],
                ])], ['desktop' => ['paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->section([$packages], ['desktop' => ['backgroundColor' => '#f7f7f8', 'paddingTop' => 44, 'paddingBottom' => 44], 'mobile' => ['paddingTop' => 28, 'paddingBottom' => 28]]),
                $b->section([$b->guarantee($b->t('নিশ্চিন্তে অর্ডার করুন', 'Order with confidence'), $b->t('অর্ডারের পর আপনার সব তথ্য আমাদের কাছে নিরাপদ থাকে।', 'Your details stay safe with us once you order.'))], ['desktop' => ['paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->section([$b->orderFaq()], ['desktop' => ['backgroundColor' => '#f7f7f8', 'paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->checkoutSection($b->t('অর্ডার করুন এখনই', 'Place your order now')),
                $b->sticky($b->t('অর্ডার করুন', 'Order now'), null, 'offer_1'),
            ],
            'slots' => [
                $this->slot($b, 'main', 'আপনার পণ্য', 'Your product', ['offer_1' => 1, 'offer_2' => 2, 'offer_3' => 3], true, 'একই পণ্য ১টি, ২টি ও ৩টির প্যাকেজে দেখানো হবে।', 'The same product is offered as 1, 2 and 3 pieces.'),
            ],
            'todos' => [
                $b->t('২টি ও ৩টির প্যাকেজের ছাড় (১০% ও ১৫%) নমুনা মাত্র — নিজের ছাড় ঠিক করুন।', 'The pack discounts (10% and 15%) are examples. Set your own.'),
            ],
        ];
    }
}
