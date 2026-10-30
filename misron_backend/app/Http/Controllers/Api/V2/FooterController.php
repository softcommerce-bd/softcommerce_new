<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessSetting;

class FooterController extends Controller
{
   
    public function index()
    {
        try {
            // Helper function to get decoded JSON setting
            $getDecodedSetting = function ($type, $default = []) {
                $setting = BusinessSetting::where('type', $type)->first();
                if ($setting && $setting->value) {
                    $decoded = json_decode($setting->value, true);
                    return is_array($decoded) ? $decoded : $default;
                }
                return $default;
            };

            // Process menu items helper
            $processMenu = function ($labels, $links) {
                $menu = [];
                if (!empty($labels) && is_array($labels)) {
                    foreach ($labels as $index => $label) {
                        if (!empty($label)) {
                            $menu[] = [
                                'label' => trim($label),
                                'link' => isset($links[$index]) && !empty($links[$index])
                                    ? parse_url($links[$index], PHP_URL_PATH)
                                    : '#'
                            ];
                        }
                    }
                }
                return $menu;
            };

            // Get footer logo
            $footerLogo = get_setting('footer_logo');
            $footerLogoUrl = $footerLogo ? uploaded_asset($footerLogo) : null;

            // Get payment methods image
            $paymentMethodsImage = get_setting('payment_method_images');
            $paymentMethodsImageUrl = $paymentMethodsImage ? uploaded_asset($paymentMethodsImage) : null;

            // Get menu data - USING CORRECT TYPES FROM BLADE TEMPLATE
         
            $usefulLinksLabels = $getDecodedSetting('footer_menu_labels');
            $usefulLinksLinks = $getDecodedSetting('footer_menu_links');

            // Process menus
       
            $usefulLinks = $processMenu($usefulLinksLabels, $usefulLinksLinks);

            // Add flash deals link
            $usefulLinks[] = [
                'label' => 'Flash Deals',
                'link' => '/flash-deal'
            ];

            // Get contact information
            $contactAddress = get_setting('contact_address', null, app()->getLocale());
            $contactPhone = get_setting('contact_phone');
            $contactEmail = get_setting('contact_email');

            // Get social links
            $socialLinks = [];
            $showSocialLinks = get_setting('show_social_links', false);

            if ($showSocialLinks) {
                $socialPlatforms = ['facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'tiktok'];

                foreach ($socialPlatforms as $platform) {
                    $link = get_setting($platform . '_link');
                    if ($link) {
                        $socialLinks[] = [
                            'platform' => $platform,
                            'url' => $link,
                            'icon' => $this->getSocialIcon($platform) // You can create this helper method
                        ];
                    }
                }
            }

            // Build response
            $data = [
                'logo' => $footerLogoUrl,
                'footer_color' => get_setting('footer_color'),
                // Sourced from Website Setup > Footer > About Widget > "About description".
                'footer_description' => get_setting('about_us_description', null, app()->getLocale()),
                'copyright' => get_setting('frontend_copyright_text', null, app()->getLocale()),
                'payment_methods_image' => $paymentMethodsImageUrl,
                'useful_links_menu' => $usefulLinks,
                'contact_address' => $contactAddress,
                'contact_phone' => $contactPhone,
                'contact_email' => $contactEmail,
                'show_social_links' => (bool) $showSocialLinks,
                'social_links' => $socialLinks
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Footer API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error fetching footer data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get social media icon names or paths
     */
    private function getSocialIcon($platform)
    {
        $icons = [
            'facebook' => 'facebook',
            'twitter' => 'twitter',
            'instagram' => 'instagram',
            'youtube' => 'youtube',
            'linkedin' => 'linkedin',
            'tiktok' => 'tiktok'
        ];

        return $icons[$platform] ?? $platform;
    }
}
