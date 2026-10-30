<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\SliderCollection;
use Cache;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\Upload;

class SliderController extends Controller
{
  public function sliders()
{
    $get_images = get_setting('home_slider_images', null, request()->header('App-Language'));
    $images = $get_images != null ? json_decode($get_images, true) : [];
    $get_links = get_setting('home_slider_links', null, request()->header('App-Language'));
    $get_title_one = get_setting('home_slider_title_one', null, request()->header('App-Language'));
    $get_title_two = get_setting('home_slider_title_two', null, request()->header('App-Language'));
    
    $links = ($get_images != null && $get_links != null) ? json_decode($get_links, true) : [];
    $title_one = ($get_title_one != null) ? json_decode($get_title_one, true) : [];
    $title_two = ($get_title_two != null) ? json_decode($get_title_two, true) : [];
    
    $sliders = [];
    for ($i = 0; $i < count($images); $i++) {
        $sliders[$i] = [
            'link' => $links[$i] ?? null,
            'image' => $images[$i],
            'title_one' => $title_one[$i] ?? null,
            'title_two' => $title_two[$i] ?? null
        ];
    }

    // Two fixed images shown beside the slider. Each is a single upload, so
    // an unset slot is null rather than the placeholder uploaded_asset() returns.
    $side_images = [];
    foreach (['home_slider_side_image_1', 'home_slider_side_image_2'] as $key) {
        $asset_id = get_setting($key);
        $side_images[] = $asset_id ? ['photo' => uploaded_asset($asset_id)] : null;
    }

    return (new SliderCollection($sliders))->additional(['side_images' => $side_images]);
}
    // todays deal banner,top banner ,bottom banner and links method
    public function getTodaysDeal()
{
    // dd('hello');
    // $todays_deal_products = filter_products(Product::where('todays_deal', '1'))
    //     ->orderBy('id', 'desc')
    //     ->get();
        $lang = get_system_language()->code;
        $todays_deal_banner_get = get_setting('todays_deal_banner', null, $lang);
        $todays_deal_banner = uploaded_asset($todays_deal_banner_get);
        $todays_deal_banner_small = get_setting('todays_deal_banner_small', null, $lang);
        
        // Corrected array assignment for today's deal image
        $todays_deal_single_image_links = [
            'image' => $todays_deal_banner ?? null,
            'link'  => url('/todays-deal')
        ];
                

    $todays_deal_top_slider = BusinessSetting::where('type', 'todays_deal_top_images')->first();
    $todays_deal_top_slider_links = BusinessSetting::where('type', 'todays_deal_top_links')->first();
    $todays_deal_bottom_slider = BusinessSetting::where('type', 'todays_deal_bottom_images')->first();
    $todays_deal_bottom_slider_links = BusinessSetting::where('type', 'todays_deal_bottom_links')->first();

    // Decode image IDs and links
    $top_image_ids = $todays_deal_top_slider ? json_decode($todays_deal_top_slider->value, true) : [];
    $top_links = $todays_deal_top_slider_links ? json_decode($todays_deal_top_slider_links->value, true) : [];
    $bottom_image_ids = $todays_deal_bottom_slider ? json_decode($todays_deal_bottom_slider->value, true) : [];
    $bottom_links = $todays_deal_bottom_slider_links ? json_decode($todays_deal_bottom_slider_links->value, true) : [];

    // Fetch images using Upload model
    $top_images = Upload::whereIn('id', $top_image_ids)->get()->keyBy('id');
    $bottom_images = Upload::whereIn('id', $bottom_image_ids)->get()->keyBy('id');

    // Prepare top slider data
    $top_slider_data = [];
    foreach ($top_image_ids as $key => $image_id) {
        $top_slider_data[] = [
            'image' => url('public/' . $top_images[$image_id]->file_name ?? ''), // Add base URL here
            'link' => $top_links[$key] ?? '#',
        ];
    }

    // Prepare bottom slider data with base URL for images
    $bottom_slider_data = [];
    foreach ($bottom_image_ids as $key => $image_id) {
        $bottom_slider_data[] = [
            'image' => url('public/' . $bottom_images[$image_id]->file_name ?? ''), // Add base URL here
            'link' => $bottom_links[$key] ?? '#',
        ];
    }

        return response()->json([
        "success" => true,
        "status" => 200,
        "top_slider" => $top_slider_data,
        "bottom_slider" => $bottom_slider_data,
        "todays_deal" => $todays_deal_single_image_links,
    ]);
}

    public function bannerOne()
    {
        $getImages = get_setting('home_banner1_images', null, request()->header('App-Language'));
        $images = $getImages != null ? json_decode($getImages, true) : [];
        $getLinks = get_setting('home_banner1_links', null, request()->header('App-Language'));
        $links = ($getImages != null && $getLinks != null) ? json_decode($getLinks, true) : [];

        $banners = [];
        for ($i = 0; $i < count($images); $i++) {
            $banners[$i] = ['link' => $links[$i], "image" => $images[$i]];
        }
        return new SliderCollection($banners);
    }

    public function bannerTwo()
    {
        $getImages = get_setting('home_banner2_images', null, request()->header('App-Language'));
        $images = $getImages != null ? json_decode($getImages, true) : [];
        $getLinks = get_setting('home_banner2_links', null, request()->header('App-Language'));
        $links = ($getImages != null && $getLinks != null) ? json_decode($getLinks, true) : [];

        $banners = [];
        for ($i = 0; $i < count($images); $i++) {
            $banners[$i] = ['link' => $links[$i], "image" => $images[$i]];
        }
        return new SliderCollection($banners);
    }

    public function bannerThree()
    {
        $getImages = get_setting('home_banner3_images', null, request()->header('App-Language'));
        $images = $getImages != null ? json_decode($getImages, true) : [];
        $getLinks = get_setting('home_banner3_links', null, request()->header('App-Language'));
        $links = ($getImages != null && $getLinks != null) ? json_decode($getLinks, true) : [];

        $banners = [];
        for ($i = 0; $i < count($images); $i++) {
            $banners[$i] = ['link' => $links[$i], "image" => $images[$i]];
        }
        return new SliderCollection($banners);
    }
}
