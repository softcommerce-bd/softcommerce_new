<?php

namespace App\Services\Funnel\Templates\Definitions;

use App\Services\Funnel\Templates\Builder;
use App\Services\Funnel\Templates\Template;

/**
 * Lets a video explain the product: video first, then the offer. The video
 * link is a placeholder the page cannot be published without replacing.
 */
class VideoStory extends Template
{
    public function key(): string
    {
        return 'video-story';
    }

    public function name(string $lang): string
    {
        return $lang === 'en' ? 'Video story' : 'ভিডিও স্টোরি';
    }

    public function description(string $lang): string
    {
        return $lang === 'en'
            ? 'A video leads the page, followed by the benefits and a single offer. For products that are easier to show than to describe.'
            : 'পেজের শুরুতে ভিডিও, তারপর সুবিধা আর একটি অফার। যে পণ্য বোঝানোর চেয়ে দেখানো সহজ, তার জন্য।';
    }

    public function tags(string $lang): array
    {
        return $lang === 'en' ? ['Video', 'Demo'] : ['ভিডিও', 'ডেমো'];
    }

    public function build(Builder $b): array
    {
        return [
            'settings' => $b->settings(['accentColor' => '#5b3df5', 'backgroundColor' => '#ffffff']),
            'sections' => [
                $b->node('video_hero', [
                    'provider' => 'youtube',
                    'headline' => $b->t('দেখুন কীভাবে কাজ করে', 'See how it works'),
                    'subheadline' => $b->t('এক মিনিটের ভিডিওতেই বুঝে নিন কেন এটি আপনার দরকার।', 'One short video shows why you need it.'),
                    'ctaText' => $b->t('অর্ডার করুন', 'Order now'),
                    'ctaAction' => 'checkout',
                ], ['desktop' => ['backgroundColor' => '#f4f1ff', 'paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->section([
                    $b->columns([
                        [$b->node('product_gallery', ['offerKey' => 'offer', 'layout' => 'thumbs_bottom'])],
                        [
                            $b->heading($b->t('আজকের অফার', "Today's offer"), 'h2'),
                            $b->node('product_card', ['layout' => 'horizontal', 'showDescription' => false, 'ctaText' => $b->t('সিলেক্ট করুন', 'Select'), 'ctaAction' => 'select'], [], null, ['productBindings' => [
                                $b->binding('offer', $b->t('এই পণ্যটি', 'This product'), ['type' => 'none'], ['defaultSelected' => true]),
                            ]]),
                            $b->node('product_variations', ['offerKey' => 'offer', 'display' => 'buttons']),
                            $b->node('quantity_selector', ['offerKey' => 'offer', 'max' => 10]),
                            $b->button($b->t('অর্ডার করুন', 'Order now'), 'checkout', ['desktop' => ['buttonSize' => 'xl'], 'mobile' => ['fullWidth' => true]]),
                        ],
                    ], ['desktop' => ['gap' => 40, 'alignItems' => 'center'], 'mobile' => ['gap' => 20]], ['stackOn' => 'mobile']),
                ], ['desktop' => ['paddingTop' => 40, 'paddingBottom' => 40], 'mobile' => ['paddingTop' => 24, 'paddingBottom' => 24]]),
                $b->section([$b->benefits($b->t('ভিডিওতে যা দেখলেন', 'What you saw in the video'), [
                    ['zap', $b->t('দ্রুত কাজ', 'Works fast'), $b->t('সময় বাঁচায়, ঝামেলা কমায়।', 'Saves time and removes hassle.')],
                    ['badge-check', $b->t('নির্ভরযোগ্য', 'Reliable'), $b->t('প্রতিদিনের ব্যবহারের জন্য তৈরি।', 'Made for everyday use.')],
                    ['heart', $b->t('পছন্দের', 'Well liked'), $b->t('যারা ব্যবহার করেছেন তাদের কাছে প্রিয়।', 'A favourite of those who use it.')],
                ])], ['desktop' => ['backgroundColor' => '#f7f7f8', 'paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->section([$b->orderFaq()], ['desktop' => ['paddingTop' => 40, 'paddingBottom' => 40]]),
                $b->checkoutSection($b->t('অর্ডার করতে নিচের ফর্মটি পূরণ করুন', 'Fill in the form to order'), ['desktop' => ['backgroundColor' => '#f4f1ff']]),
                $b->sticky($b->t('অর্ডার করুন', 'Order now'), null, 'offer'),
            ],
            'slots' => [
                $this->slot($b, 'main', 'আপনার পণ্য', 'Your product', ['offer' => 1], true),
            ],
            'todos' => [
                $b->t('ভিডিওর লিংক বসান (YouTube, Facebook বা Vimeo)। বসানো না হলে পেজ প্রকাশ করা যাবে না।', 'Paste your video link (YouTube, Facebook or Vimeo). The page cannot be published without it.'),
            ],
        ];
    }
}
