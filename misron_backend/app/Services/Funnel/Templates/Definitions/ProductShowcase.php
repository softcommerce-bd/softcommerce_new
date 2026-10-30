<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * A small collection on one page: four products as cards, customers tick the
 * ones they want, and one order form takes them all.
 */
class ProductShowcase extends Template
{
    public function key(): string
    {
        return 'product-showcase';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Product showcase' : 'প্রোডাক্ট শোকেস';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'Four products as cards. Customers tick the ones they want and place one order for all of them.'
            : 'চারটি পণ্য কার্ড আকারে। ক্রেতা পছন্দের পণ্যে টিক দেন আর একটি অর্ডারেই সব নেন।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['Several products', 'Collection'] : ['একাধিক পণ্য', 'কালেকশন'];
    }

    public function build(Builder $b): array
    {
        $cards = [];
        $slots = [];
        for ($i = 1; $i <= 4; $i++) {
            $cards[] = $b->binding("product_{$i}", '', ['type' => 'none']);
            $slots[] = $this->slot($b, "product_{$i}", "পণ্য {$i}", "Product {$i}", ["product_{$i}" => 1], true);
        }

        return [
            'settings' => $b->settings(['accentColor' => '#2563eb', 'backgroundColor' => '#ffffff', 'maxWidth' => 1200]),
            'sections' => [
                $b->announcement($b->t('পছন্দের পণ্যে টিক দিন, নিচে একসাথে অর্ডার করুন', 'Tick what you like and order everything together below'), 'shopping-bag'),
                $b->section([
                    $b->heading($b->t('আমাদের বাছাই করা কালেকশন', 'Our selected collection'), 'h1', ['desktop' => ['textAlign' => 'center', 'fontSize' => 36], 'mobile' => ['fontSize' => 26, 'textAlign' => 'center']]),
                    $b->text($b->t('এক বা একাধিক পণ্য বেছে নিন। মোট দাম নিচের সারাংশে দেখতে পাবেন।', 'Choose one or more products. You see the total in the summary below.'), ['desktop' => ['textAlign' => 'center', 'marginBottom' => 8]]),
                    $b->node('product_offer_selector', [
                        'selectionMode' => 'multiple',
                        'cardLayout' => 'vertical',
                        'showOriginalPrice' => true,
                        'showSavings' => true,
                        'allowQuantity' => true,
                    ], ['desktop' => ['columns' => 4, 'gap' => 16], 'tablet' => ['columns' => 2], 'mobile' => ['columns' => 2, 'gap' => 10]], null, ['productBindings' => $cards]),
                ], ['desktop' => ['paddingTop' => 44, 'paddingBottom' => 44], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->section([$b->usp([
                    ['truck', $b->t('একসাথে ডেলিভারি', 'One delivery')],
                    ['wallet', $b->t('সহজ পেমেন্ট', 'Easy payment')],
                    ['badge-check', $b->t('আসল পণ্য', 'Genuine products')],
                ])], ['desktop' => ['backgroundColor' => '#f1f5ff', 'paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->checkoutSection($b->t('আপনার অর্ডার', 'Your order'), ['desktop' => ['paddingTop' => 44]]),
                $b->sticky($b->t('অর্ডার করুন', 'Order now')),
            ],
            'slots' => $slots,
            'todos' => [
                $b->t('চারটির কম পণ্য রাখতে চাইলে কার্ড ও তার ঘরটি “পণ্য” ব্লক থেকে মুছে দিন।', 'To show fewer than four products, remove the extra products from the product block.'),
            ],
        ];
    }
}
