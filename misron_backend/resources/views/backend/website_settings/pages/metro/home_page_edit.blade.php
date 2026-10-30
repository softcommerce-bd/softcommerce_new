@extends('backend.layouts.app')

@section('content')
    <div class="page-content">
        <div class="aiz-titlebar text-left mt-2 pb-2 px-3 px-md-2rem border-bottom border-gray">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3">{{ translate('Homepage Settings (Metro)') }}</h1>
                </div>
                {{-- <div class="col text-right">
					<a class="btn has-transition btn-xs p-0 hov-svg-danger" href="{{ route('home') }}" 
						target="_blank" data-toggle="tooltip" data-placement="top" data-title="{{ translate('View Tutorial Video') }}">
						<svg xmlns="http://www.w3.org/2000/svg" width="19.887" height="16" viewBox="0 0 19.887 16">
							<path id="_42fbab5a39cb8436403668a76e5a774b" data-name="42fbab5a39cb8436403668a76e5a774b" d="M18.723,8H5.5A3.333,3.333,0,0,0,2.17,11.333v9.333A3.333,3.333,0,0,0,5.5,24h13.22a3.333,3.333,0,0,0,3.333-3.333V11.333A3.333,3.333,0,0,0,18.723,8Zm-3.04,8.88-5.47,2.933a1,1,0,0,1-1.473-.88V13.067a1,1,0,0,1,1.473-.88l5.47,2.933a1,1,0,0,1,0,1.76Zm-5.61-3.257L14.5,16l-4.43,2.377Z" transform="translate(-2.17 -8)" fill="#9da3ae"/>
						</svg>
					</a>
				</div> --}}
            </div>
        </div>

        <div class="d-sm-flex">
            <!-- page side nav -->
            <div class="page-side-nav c-scrollbar-light px-3 py-2">
                <ul class="nav nav-tabs flex-sm-column border-0" role="tablist" aria-orientation="vertical">
                    <!-- Home Topbar -->
                    <li class="nav-item">
                        <a class="nav-link" id="home-topbar-tab" href="#home_topbar" data-toggle="tab"
                            data-target="#home_topbar" type="button" role="tab" aria-controls="home_topbar"
                            aria-selected="true">
                            {{ translate('Home Topbar') }}
                        </a>
                    </li>
                    <!-- Marquee Banner -->
                    <li class="nav-item">
                        <a class="nav-link" id="marquee-banner-tab" href="#marquee_banner" data-toggle="tab"
                            data-target="#marquee_banner" type="button" role="tab" aria-controls="marquee_banner"
                            aria-selected="true">
                            {{ translate('Marquee Banner') }}
                        </a>
                    </li>
                    <!-- Home Slider -->
                    <li class="nav-item">
                        <a class="nav-link" id="home-slider-tab" href="#home_slider" data-toggle="tab"
                            data-target="#home_slider" type="button" role="tab" aria-controls="home_slider"
                            aria-selected="true">
                            {{ translate('Home Slider') }}
                        </a>
                    </li>
                    <!-- Home new in Collection -->
                    <li class="nav-item">
                        <a class="nav-link" id="home-new-in-tab" href="#home_new_in" data-toggle="tab"
                            data-target="#home_new_in" type="button" role="tab" aria-controls="home_new_in"
                            aria-selected="true">
                            {{ translate('Home New In Collection') }}
                        </a>
                    </li>
                    <!-- Home Featured Collection -->
                    <li class="nav-item">
                        <a class="nav-link" id="home-featured-tab" href="#home_featured" data-toggle="tab"
                            data-target="#home_featured" type="button" role="tab" aria-controls="home_featured"
                            aria-selected="true">
                            {{ translate('Home Featured Collection') }}
                        </a>
                    </li>
                    <!-- Home Top Picks Collection -->
                    <li class="nav-item">
                        <a class="nav-link" id="home-top-picks-tab" href="#home_top_picks" data-toggle="tab"
                            data-target="#home_top_picks" type="button" role="tab" aria-controls="home_top_picks"
                            aria-selected="true">
                            {{ translate('Home Top Picks Collection') }}
                        </a>
                    </li>
                    <!-- Home Seasonal Collection -->
                    <li class="nav-item">
                        <a class="nav-link" id="home-seasonal-tab" href="#home_seasonal" data-toggle="tab"
                            data-target="#home_seasonal" type="button" role="tab" aria-controls="home_seasonal"
                            aria-selected="true">
                            {{ translate('Home Seasonal Collection') }}
                        </a>
                    </li>
                    <!--Mobile Home Slider -->
                    <li class="nav-item">
                        <a class="nav-link" id="mobile-home-slider-tab" href="#mobile_home_slider" data-toggle="tab"
                            data-target="#mobile_home_slider" type="button" role="tab"
                            aria-controls="mobile_home_slider" aria-selected="true">
                            {{ translate('Mobile Home Slider') }}
                        </a>
                    </li>
                    <!--Client Testimonial -->
                    <li class="nav-item">
                        <a class="nav-link" id="client-testimonial-tab" href="#client_testimonial" data-toggle="tab"
                            data-target="#client_testimonial" type="button" role="tab"
                            aria-controls="client_testimonial" aria-selected="true">
                            {{ translate('Client Testimonial') }}
                        </a>
                    </li>
                    <!-- Flash Deals -->
                    <li class="nav-item">
                        <a class="nav-link" id="flash-deals-tab" href="#flash_deals" data-toggle="tab"
                            data-target="#flash_deals" type="button" role="tab" aria-controls="flash_deals"
                            aria-selected="false">
                            {{ translate('Flash Deals') }}
                        </a>
                    </li>
                    <!-- Today's Deal -->
                    <li class="nav-item">
                        <a class="nav-link" id="todays-deal-tab" href="#todays_deal" data-toggle="tab"
                            data-target="#todays_deal" type="button" role="tab" aria-controls="todays_deal"
                            aria-selected="false">
                            {{ translate("Today's Deal") }}
                        </a>
                    </li>
                    <!-- Today's Deal top slider-->
                    <li class="nav-item">
                        <a class="nav-link" id="todays-deal-top-slider-tab" href="#todays_top_slider" data-toggle="tab"
                            data-target="#todays_top_slider" type="button" role="tab"
                            aria-controls="todays_top_slider" aria-selected="true">
                            {{ translate("Today's Deal Top Slider") }}
                        </a>
                    </li>
                    <!-- Today's Deal bottom slider-->
                    <li class="nav-item">
                        <a class="nav-link" id="todays-deal-bottom-slider-tab" href="#todays_bottom_slider"
                            data-toggle="tab" data-target="#todays_bottom_slider" type="button" role="tab"
                            aria-controls="todays_bottom_slider" aria-selected="true">
                            {{ translate("Today's Deal Bottom Slider") }}
                        </a>
                    </li>
                    <!-- Featured Products -->
                    {{-- <li class="nav-item">
						<a class="nav-link" aria-current="page" href="#profile"
							data-toggle="tab" data-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">
							{{ translate('Featured Products') }}
						</a>
					</li> --}}
                    <!-- Banner Level 1 -->
                    <li class="nav-item">
                        <a class="nav-link" id="banner-1-tab" href="#banner_1" data-toggle="tab"
                            data-target="#banner_1" type="button" role="tab" aria-controls="banner_1"
                            aria-selected="false">
                            {{ translate('Banner Level 1') }}
                        </a>
                    </li>
                    <!-- Featured Categories -->
                    {{-- <li class="nav-item">
						<a class="nav-link" aria-current="page" href="#profile"
							data-toggle="tab" data-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">
							{{ translate('Featured Categories') }}
						</a>
					</li> --}}
                    <!-- Banner Level 2 -->
                    <li class="nav-item">
                        <a class="nav-link" id="banner-2-tab" href="#banner_2" data-toggle="tab"
                            data-target="#banner_2" type="button" role="tab" aria-controls="banner_2"
                            aria-selected="false">
                            {{ translate('Banner Level 2') }}
                        </a>
                    </li>
                    <!-- Best Selling -->
                    {{-- <li class="nav-item">
						<a class="nav-link" aria-current="page" href="#profile"
							data-toggle="tab" data-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">
							{{ translate('Best Selling') }}
						</a>
					</li> --}}
                    <!-- New Products -->
                    {{-- <li class="nav-item">
						<a class="nav-link" aria-current="page" href="#profile"
							data-toggle="tab" data-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">
							{{ translate('New Products') }}
						</a>
					</li> --}}
                    <!-- Banner Level 3 -->
                    <li class="nav-item">
                        <a class="nav-link" id="banner-3-tab" href="#banner_3" data-toggle="tab"
                            data-target="#banner_3" type="button" role="tab" aria-controls="banner_3"
                            aria-selected="false">
                            {{ translate('Banner Level 3') }}
                        </a>
                    </li>
                    @if (addon_is_activated('auction'))
                        <!-- Auction Products -->
                        <li class="nav-item">
                            <a class="nav-link" id="auction-tab" href="#auction" data-toggle="tab"
                                data-target="#auction" type="button" role="tab" aria-controls="auction"
                                aria-selected="false">
                                {{ translate('Auction Products') }}
                                @if (env('DEMO_MODE') == 'On')
                                    <span class="badge badge-pill badge-secondary ml-1">{{ translate('Addon') }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                    @if (get_setting('coupon_system') == 1)
                        <!-- Coupon Section -->
                        <li class="nav-item">
                            <a class="nav-link" id="coupon-tab" href="#coupon" data-toggle="tab" data-target="#coupon"
                                type="button" role="tab" aria-controls="coupon" aria-selected="false">
                                {{ translate('Coupon Section') }}
                            </a>
                        </li>
                    @endif
                    <!-- Category Wise Products -->
                    <li class="nav-item">
                        <a class="nav-link" id="home-categories-tab" href="#home_categories" data-toggle="tab"
                            data-target="#home_categories" type="button" role="tab" aria-controls="home_categories"
                            aria-selected="false">
                            {{ translate('Category Wise Products') }}
                        </a>
                    </li>
                    <!-- Digital Products -->
                    {{-- <li class="nav-item">
						<a class="nav-link" aria-current="page" href="#profile"
							data-toggle="tab" data-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">
							{{ translate('Digital Products') }}
						</a>
					</li> --}}
                    <!-- Classifieds -->
                    <li class="nav-item">
                        <a class="nav-link" id="classifiedss-tab" href="#classifieds" data-toggle="tab"
                            data-target="#classifieds" type="button" role="tab" aria-controls="classifieds"
                            aria-selected="false">
                            {{ translate('Classifieds') }}
                        </a>
                    </li>
                    <!-- Top Sellers -->
                    {{-- <li class="nav-item">
						<a class="nav-link" aria-current="page" href="#profile"
							data-toggle="tab" data-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">
							{{ translate('Top Sellers') }}
						</a>
					</li> --}}
                    <!-- Top Brands -->
                    <li class="nav-item">
                        <a class="nav-link" id="brands-tab" href="#brands" data-toggle="tab" data-target="#brands"
                            type="button" role="tab" aria-controls="brands" aria-selected="false">
                            {{ translate('Top Brands') }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- tab content -->
            <div class="flex-grow-1 p-sm-3 p-lg-2rem mb-2rem mb-md-0">
                <div class="tab-content">

                    <!-- Language Bar -->
                    <ul class="nav nav-tabs nav-fill language-bar">
                        @foreach (get_all_active_language() as $key => $language)
                            <li class="nav-item">
                                <a class="nav-link text-reset @if ($language->code == $lang) active @endif py-3"
                                    href="{{ route('custom-pages.edit', ['id' => $page->slug, 'lang' => $language->code, 'page' => 'home']) }}">
                                    <img src="{{ static_asset('assets/img/flags/' . $language->code . '.png') }}"
                                        height="11" class="mr-1">
                                    <span>{{ $language->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <!--Topbar Title -->
                    <div class="tab-pane fade py-3" id="home_topbar" role="tabpanel" aria-labelledby="home-topbar-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="home_topbar">
                            <!-- Title -->
                            <div class="form-group">
                                <label>{{ translate('Topbar Title') }} ({{ translate('Translatable') }})</label>
                                <input type="hidden" name="types[][{{ $lang }}]" value="topbar_title">
                                <input type="text" class="form-control" placeholder="Topbar title"
                                    name="topbar_title" value="{{ get_setting('topbar_title', null, $lang) }}">
                            </div>
                            <!-- Promo message. `{code}` marks where the code badge sits. -->
                            <div class="form-group">
                                <label>{{ translate('Topbar Promo Message') }} ({{ translate('Translatable') }})</label>
                                <input type="hidden" name="types[][{{ $lang }}]" value="topbar_promo_text">
                                <input type="text" class="form-control"
                                    placeholder="Special Welcome: Use code {code} for 10% off your order!"
                                    name="topbar_promo_text" value="{{ get_setting('topbar_promo_text', null, $lang) }}">
                                <small class="text-muted">{{ translate('Text in { } becomes a badge: {code} shows the promo code below, {SAVE10} shows SAVE10. Leave empty to hide the promo.') }}</small>
                            </div>
                            <!-- Promo code -->
                            <div class="form-group">
                                <label>{{ translate('Topbar Promo Code') }}</label>
                                <input type="hidden" name="types[]" value="topbar_promo_code">
                                <input type="text" class="form-control" placeholder="WELCOME10"
                                    name="topbar_promo_code" value="{{ get_setting('topbar_promo_code') }}">
                            </div>
                            <!-- Update Button -->
                            <div class="mt-4 text-right">
                                <button type="submit"
                                    class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Update') }}</button>
                            </div>
                        </form>
                    </div>
                    <!-- Marquee Banner -->
                    {{--
                        The scrolling band under the header. Unlike the tabs
                        around it these fields are not translatable: the
                        storefront reads them from the public
                        `/api/v2/business-settings` endpoint, which returns
                        every row without its `lang`, so per-language rows of
                        one type would be indistinguishable there. One row per
                        setting, `lang` null, keeps that lookup unambiguous.

                        `marquee_items[]` posts as an array, which the settings
                        handler json_encodes before saving.
                    --}}
                    <div class="tab-pane fade py-3" id="marquee_banner" role="tabpanel"
                        aria-labelledby="marquee-banner-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="marquee_banner">

                            <div class="bg-white p-3 p-sm-2rem">
                                <!-- Show the band -->
                                <div class="form-group row">
                                    <label
                                        class="col-md-3 col-from-label">{{ translate('Show Marquee Banner?') }}</label>
                                    <div class="col-md-8">
                                        <label class="aiz-switch aiz-switch-success mb-0">
                                            <input type="hidden" name="types[]" value="marquee_enabled">
                                            <input type="hidden" name="marquee_enabled" value="off">
                                            <input type="checkbox" name="marquee_enabled" value="on"
                                                @if (get_setting('marquee_enabled') == 'on') checked @endif>
                                            <span></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Phrases -->
                                <div class="form-group row">
                                    <label class="col-md-3 col-from-label">{{ translate('Phrases') }}</label>
                                    <div class="col-md-8">
                                        <input type="hidden" name="types[]" value="marquee_items">

                                        <div class="marquee-items-target">
                                            @php
                                                $marquee_items = json_decode(get_setting('marquee_items'), true);
                                                if (!is_array($marquee_items) || count($marquee_items) == 0) {
                                                    $marquee_items = [''];
                                                }
                                            @endphp

                                            @foreach ($marquee_items as $marquee_item)
                                                <div class="row gutters-5">
                                                    <div class="col">
                                                        <div class="form-group">
                                                            <input type="text" class="form-control"
                                                                placeholder="{{ translate('Free delivery available') }}"
                                                                name="marquee_items[]" value="{{ $marquee_item }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <button type="button"
                                                            class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                            data-toggle="remove-parent" data-parent=".row">
                                                            <i class="las la-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <button type="button" class="btn btn-soft-secondary btn-sm"
                                            data-toggle="add-more"
                                            data-content='<div class="row gutters-5">
                                                <div class="col">
                                                    <div class="form-group">
                                                        <input type="text" class="form-control" placeholder="{{ translate('Free delivery available') }}" name="marquee_items[]">
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".row">
                                                        <i class="las la-times"></i>
                                                    </button>
                                                </div>
                                            </div>'
                                            data-target=".marquee-items-target">
                                            {{ translate('Add New') }}
                                        </button>

                                        <small class="d-block mt-2 text-muted">
                                            {{ translate('Each phrase is shown as one item in the scrolling band, separated by a dot. Keep them short.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Button -->
                            <div class="mt-4 text-right">
                                <button type="submit"
                                    class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Update') }}</button>
                            </div>
                        </form>
                    </div>
                    <!-- Home Slider -->
                    <div class="tab-pane fade" id="home_slider" role="tabpanel" aria-labelledby="home-slider-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="home_slider">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_slider_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_slider_links">
                            <input type="hidden" name="types[]" value="home_slider_side_image_1">
                            <input type="hidden" name="types[]" value="home_slider_side_image_2">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Side images shown beside the slider -->
                                    <div class="mb-4 pb-3 border-bottom">
                                        <h6 class="fs-14 fw-700 mb-1">{{ translate('Side Images') }}</h6>
                                        <div class="fs-11 text-gray mb-3">
                                            {{ translate('Two images shown beside the slider on the home page. Recommended 800 px X 400 px. They are not shown until uploaded.') }}
                                        </div>
                                        <div class="row gutters-5">
                                            @foreach ([1, 2] as $side)
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3 mb-md-0">
                                                        <label class="fs-13 fw-600">{{ translate('Side Image') }} {{ $side }}</label>
                                                        <div class="input-group" data-toggle="aizuploader"
                                                            data-type="image">
                                                            <div class="input-group-prepend">
                                                                <div
                                                                    class="input-group-text bg-soft-secondary font-weight-medium">
                                                                    {{ translate('Browse') }}</div>
                                                            </div>
                                                            <div class="form-control file-amount">
                                                                {{ translate('Choose File') }}</div>
                                                            <input type="hidden"
                                                                name="home_slider_side_image_{{ $side }}"
                                                                class="selected-files"
                                                                value="{{ get_setting('home_slider_side_image_' . $side) }}">
                                                        </div>
                                                        <div class="file-preview box sm">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">{{ translate('Minimum 664 px or higher  X 490px') }}</div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="home-slider-target">
                                        @php
                                            $home_slider_images = get_setting('home_slider_images', null, $lang);
                                            $home_slider_links = get_setting('home_slider_links', null, $lang);
                                        @endphp
                                        @if ($home_slider_images != null)
                                            @foreach (json_decode($home_slider_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden" name="home_slider_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($home_slider_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://" name="home_slider_links[]"
                                                                    value="{{ isset(json_decode($home_slider_links, true)[$key]) ? json_decode($home_slider_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="home_slider_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0">
															<input type="text" class="form-control" placeholder="http://" name="home_slider_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".home-slider-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Home New In -->
                    <div class="tab-pane fade" id="home_new_in" role="tabpanel" aria-labelledby="home-new-in-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="home_new_in">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_new_in_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_new_in_title">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">{{ translate('Minimum 360 px or higher  X 530px') }}</div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="home-new-in-target">
                                        @php
                                            $home_new_in_images = get_setting('home_new_in_images', null, $lang);
                                            $home_new_in_title = get_setting('home_new_in_title', null, $lang);
                                        @endphp

                                        @if ($home_new_in_images != null && !empty(json_decode($home_new_in_images, true)))
                                            @foreach (json_decode($home_new_in_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ $value ? basename($value) : translate('Choose File') }}
                                                                    </div>
                                                                    <input type="hidden" name="home_new_in_images[]"
                                                                        class="selected-files"
                                                                        value="{{ $value }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                    @if ($value)
                                                                        <img src="{{ uploaded_asset($value) }}"
                                                                            alt="Preview"
                                                                            style="max-width: 100px; max-height: 100px; margin-top: 10px;">
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- title -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="Title" name="home_new_in_title[]"
                                                                    value="{{ isset(json_decode($home_new_in_title, true)[$key]) ? json_decode($home_new_in_title, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <!-- Show blank fields when no data exists -->
                                            <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                style="border: 1px dashed #e4e5eb;">
                                                <div class="row gutters-5">
                                                    <!-- Image -->
                                                    <div class="col-md-5">
                                                        <div class="form-group mb-md-0">
                                                            <div class="input-group" data-toggle="aizuploader"
                                                                data-type="image">
                                                                <div class="input-group-prepend">
                                                                    <div
                                                                        class="input-group-text bg-soft-secondary font-weight-medium">
                                                                        {{ translate('Browse') }}</div>
                                                                </div>
                                                                <div class="form-control file-amount">
                                                                    {{ translate('Choose File') }}</div>
                                                                <input type="hidden" name="home_new_in_images[]"
                                                                    class="selected-files" value="">
                                                            </div>
                                                            <div class="file-preview box sm">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- title -->
                                                    <div class="col-md">
                                                        <div class="form-group mb-md-0">
                                                            <input type="text" class="form-control"
                                                                placeholder="Title" name="home_new_in_title[]"
                                                                value="">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Save Button -->
                                    <div class="mt-4 text-right">
                                        <button type="submit"
                                            class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Home Featured -->
                    <div class="tab-pane fade" id="home_featured" role="tabpanel" aria-labelledby="home-featured-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="home_featured">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_featured_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_featured_title">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">{{ translate('Minimum 360 px or higher  X 530px') }}</div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="home-featured-target">
                                        @php
                                            $home_featured_images = get_setting('home_featured_images', null, $lang);
                                            $home_featured_title = get_setting('home_featured_title', null, $lang);
                                        @endphp

                                        @if ($home_featured_images != null && !empty(json_decode($home_featured_images, true)))
                                            @foreach (json_decode($home_featured_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ $value ? basename($value) : translate('Choose File') }}
                                                                    </div>
                                                                    <input type="hidden" name="home_featured_images[]"
                                                                        class="selected-files"
                                                                        value="{{ $value }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                    @if ($value)
                                                                        <img src="{{ uploaded_asset($value) }}"
                                                                            alt="Preview"
                                                                            style="max-width: 100px; max-height: 100px; margin-top: 10px;">
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- title -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="Title" name="home_featured_title[]"
                                                                    value="{{ isset(json_decode($home_featured_title, true)[$key]) ? json_decode($home_featured_title, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <!-- Show blank fields when no data exists -->
                                            <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                style="border: 1px dashed #e4e5eb;">
                                                <div class="row gutters-5">
                                                    <!-- Image -->
                                                    <div class="col-md-5">
                                                        <div class="form-group mb-md-0">
                                                            <div class="input-group" data-toggle="aizuploader"
                                                                data-type="image">
                                                                <div class="input-group-prepend">
                                                                    <div
                                                                        class="input-group-text bg-soft-secondary font-weight-medium">
                                                                        {{ translate('Browse') }}</div>
                                                                </div>
                                                                <div class="form-control file-amount">
                                                                    {{ translate('Choose File') }}</div>
                                                                <input type="hidden" name="home_featured_images[]"
                                                                    class="selected-files" value="">
                                                            </div>
                                                            <div class="file-preview box sm">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- title -->
                                                    <div class="col-md">
                                                        <div class="form-group mb-md-0">
                                                            <input type="text" class="form-control"
                                                                placeholder="Title" name="home_featured_title[]"
                                                                value="">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Save Button -->
                                    <div class="mt-4 text-right">
                                        <button type="submit"
                                            class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Home Top Picks -->
                    <div class="tab-pane fade" id="home_top_picks" role="tabpanel" aria-labelledby="home-top-picks-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="home_top_picks">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_top_picks_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_top_picks_title">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">{{ translate('Minimum 360 px or higher  X 530px') }}</div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="home-top-picks-target">
                                        @php
                                            $home_top_picks_images = get_setting('home_top_picks_images', null, $lang);
                                            $home_top_picks_title = get_setting('home_top_picks_title', null, $lang);
                                        @endphp

                                        @if ($home_top_picks_images != null && !empty(json_decode($home_top_picks_images, true)))
                                            @foreach (json_decode($home_top_picks_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ $value ? basename($value) : translate('Choose File') }}
                                                                    </div>
                                                                    <input type="hidden" name="home_top_picks_images[]"
                                                                        class="selected-files"
                                                                        value="{{ $value }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                    @if ($value)
                                                                        <img src="{{ uploaded_asset($value) }}"
                                                                            alt="Preview"
                                                                            style="max-width: 100px; max-height: 100px; margin-top: 10px;">
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- title -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="Title" name="home_top_picks_title[]"
                                                                    value="{{ isset(json_decode($home_top_picks_title, true)[$key]) ? json_decode($home_top_picks_title, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <!-- Show blank fields when no data exists -->
                                            <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                style="border: 1px dashed #e4e5eb;">
                                                <div class="row gutters-5">
                                                    <!-- Image -->
                                                    <div class="col-md-5">
                                                        <div class="form-group mb-md-0">
                                                            <div class="input-group" data-toggle="aizuploader"
                                                                data-type="image">
                                                                <div class="input-group-prepend">
                                                                    <div
                                                                        class="input-group-text bg-soft-secondary font-weight-medium">
                                                                        {{ translate('Browse') }}</div>
                                                                </div>
                                                                <div class="form-control file-amount">
                                                                    {{ translate('Choose File') }}</div>
                                                                <input type="hidden" name="home_top_picks_images[]"
                                                                    class="selected-files" value="">
                                                            </div>
                                                            <div class="file-preview box sm">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- title -->
                                                    <div class="col-md">
                                                        <div class="form-group mb-md-0">
                                                            <input type="text" class="form-control"
                                                                placeholder="Title" name="home_top_picks_title[]"
                                                                value="">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Save Button -->
                                    <div class="mt-4 text-right">
                                        <button type="submit"
                                            class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Home Seasonal -->
                    <div class="tab-pane fade" id="home_seasonal" role="tabpanel" aria-labelledby="home-seasonal-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="home_seasonal">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_seasonal_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_seasonal_title">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">{{ translate('Minimum 360 px or higher  X 530px') }}</div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="home-seasonal-target">
                                        @php
                                            $home_seasonal_images = get_setting('home_seasonal_images', null, $lang);
                                            $home_seasonal_title = get_setting('home_seasonal_title', null, $lang);
                                        @endphp

                                        @if ($home_seasonal_images != null && !empty(json_decode($home_seasonal_images, true)))
                                            @foreach (json_decode($home_seasonal_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ $value ? basename($value) : translate('Choose File') }}
                                                                    </div>
                                                                    <input type="hidden" name="home_seasonal_images[]"
                                                                        class="selected-files"
                                                                        value="{{ $value }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                    @if ($value)
                                                                        <img src="{{ uploaded_asset($value) }}"
                                                                            alt="Preview"
                                                                            style="max-width: 100px; max-height: 100px; margin-top: 10px;">
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- title -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="Title" name="home_seasonal_title[]"
                                                                    value="{{ isset(json_decode($home_seasonal_title, true)[$key]) ? json_decode($home_seasonal_title, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <!-- Show blank fields when no data exists -->
                                            <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                style="border: 1px dashed #e4e5eb;">
                                                <div class="row gutters-5">
                                                    <!-- Image -->
                                                    <div class="col-md-5">
                                                        <div class="form-group mb-md-0">
                                                            <div class="input-group" data-toggle="aizuploader"
                                                                data-type="image">
                                                                <div class="input-group-prepend">
                                                                    <div
                                                                        class="input-group-text bg-soft-secondary font-weight-medium">
                                                                        {{ translate('Browse') }}</div>
                                                                </div>
                                                                <div class="form-control file-amount">
                                                                    {{ translate('Choose File') }}</div>
                                                                <input type="hidden" name="home_seasonal_images[]"
                                                                    class="selected-files" value="">
                                                            </div>
                                                            <div class="file-preview box sm">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- title -->
                                                    <div class="col-md">
                                                        <div class="form-group mb-md-0">
                                                            <input type="text" class="form-control"
                                                                placeholder="Title" name="home_seasonal_title[]"
                                                                value="">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Save Button -->
                                    <div class="mt-4 text-right">
                                        <button type="submit"
                                            class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Mobile Home Slider -->
                    <div class="tab-pane fade" id="mobile_home_slider" role="tabpanel"
                        aria-labelledby="mobile_home-slider-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="mobile_home_slider">
                            <input type="hidden" name="types[][{{ $lang }}]" value="mobile_home_slider_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="mobile_home_slider_links">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">{{ translate('Minimum 345 px or higher  X 420px') }}</div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="mobile-home-slider-target">
                                        @php
                                            $mobile_home_slider_images = get_setting(
                                                'mobile_home_slider_images',
                                                null,
                                                $lang,
                                            );
                                            $mobile_home_slider_links = get_setting(
                                                'mobile_home_slider_links',
                                                null,
                                                $lang,
                                            );
                                        @endphp
                                        @if ($mobile_home_slider_images != null)
                                            @foreach (json_decode($mobile_home_slider_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden"
                                                                        name="mobile_home_slider_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($mobile_home_slider_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://"
                                                                    name="mobile_home_slider_links[]"
                                                                    value="{{ isset(json_decode($mobile_home_slider_links, true)[$key]) ? json_decode($mobile_home_slider_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="mobile_home_slider_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0">
															<input type="text" class="form-control" placeholder="http://" name="mobile_home_slider_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".mobile-home-slider-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Client Testimonial -->
                    <div class="tab-pane fade" id="client_testimonial" role="tabpanel"
                        aria-labelledby="client-testimonial-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="client_testimonial">
                            <input type="hidden" name="types[][{{ $lang }}]" value="client_testimonial_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="client_testimonial_links">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">{{ translate('Minimum 345 px or higher  X 420px') }}</div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="mobile-home-slider-target">
                                        @php
                                            $client_testimonial_images = get_setting(
                                                'client_testimonial_images',
                                                null,
                                                $lang,
                                            );
                                            $client_testimonial_links = get_setting(
                                                'client_testimonial_links',
                                                null,
                                                $lang,
                                            );
                                        @endphp
                                        @if ($client_testimonial_images != null)
                                            @foreach (json_decode($client_testimonial_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden"
                                                                        name="client_testimonial_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($client_testimonial_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://"
                                                                    name="client_testimonial_links[]"
                                                                    value="{{ isset(json_decode($client_testimonial_links, true)[$key]) ? json_decode($client_testimonial_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="client_testimonial_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0">
															<input type="text" class="form-control" placeholder="http://" name="client_testimonial_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".mobile-home-slider-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Today's Deal Top Slider Slider -->
                    <div class="tab-pane fade" id="todays_top_slider" role="tabpanel"
                        aria-labelledby="todays-deal-top-slider-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="todays_top_slider">
                            <input type="hidden" name="types[][{{ $lang }}]" value="todays_deal_top_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="todays_deal_top_links">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">
                                                {{ translate('Minimum width 185 px or higher  X 185px') }}
                                            </div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="todays-top-slider-target">
                                        @php
                                            $todays_deal_top_images = get_setting(
                                                'todays_deal_top_images',
                                                null,
                                                $lang,
                                            );
                                            $todays_deal_top_links = get_setting('todays_deal_top_links', null, $lang);
                                        @endphp
                                        @if ($todays_deal_top_images != null)
                                            @foreach (json_decode($todays_deal_top_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden" name="todays_deal_top_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($todays_deal_top_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://" name="todays_deal_top_links[]"
                                                                    value="{{ isset(json_decode($todays_deal_top_links, true)[$key]) ? json_decode($todays_deal_top_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="todays_deal_top_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0">
															<input type="text" class="form-control" placeholder="http://" name="todays_deal_top_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".todays-top-slider-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Today's Deal Bottom Slider Slider -->
                    <div class="tab-pane fade" id="todays_bottom_slider" role="tabpanel"
                        aria-labelledby="todays-deal-bottom-slider-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="todays_bottom_slider">
                            <input type="hidden" name="types[][{{ $lang }}]"
                                value="todays_deal_bottom_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="todays_deal_bottom_links">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <!-- Information -->
                                    <div class="fs-11 d-flex mb-2rem">
                                        <div>
                                            <svg id="_79508b4b8c932dcad9066e2be4ca34f2"
                                                data-name="79508b4b8c932dcad9066e2be4ca34f2"
                                                xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 16 16">
                                                <path id="Path_40683" data-name="Path 40683"
                                                    d="M8,16a8,8,0,1,1,8-8A8.024,8.024,0,0,1,8,16ZM8,1.333A6.667,6.667,0,1,0,14.667,8,6.686,6.686,0,0,0,8,1.333Z"
                                                    fill="#9da3ae" />
                                                <path id="Path_40684" data-name="Path 40684"
                                                    d="M10.6,15a.926.926,0,0,1-.667-.333c-.333-.467-.067-1.133.667-2.933.133-.267.267-.6.4-.867a.714.714,0,0,1-.933-.067.644.644,0,0,1,0-.933A3.408,3.408,0,0,1,11.929,9a.926.926,0,0,1,.667.333c.333.467.067,1.133-.667,2.933-.133.267-.267.6-.4.867a.714.714,0,0,1,.933.067.644.644,0,0,1,0,.933A3.408,3.408,0,0,1,10.6,15Z"
                                                    transform="translate(-3.262 -3)" fill="#9da3ae" />
                                                <circle id="Ellipse_813" data-name="Ellipse 813" cx="1"
                                                    cy="1" r="1" transform="translate(8 3.333)" fill="#9da3ae" />
                                                <path id="Path_40685" data-name="Path 40685"
                                                    d="M12.833,7.167a1.333,1.333,0,1,1,1.333-1.333A1.337,1.337,0,0,1,12.833,7.167Zm0-2a.63.63,0,0,0-.667.667.667.667,0,1,0,1.333,0A.63.63,0,0,0,12.833,5.167Z"
                                                    transform="translate(-3.833 -1.5)" fill="#9da3ae" />
                                            </svg>
                                        </div>
                                        <div class="ml-2 text-gray">
                                            <div class="mb-2">
                                                {{ translate('Minimum width 400 px or higher  X 400px') }}
                                            </div>
                                            <div>
                                                {{ translate('We have limited banner height to maintain UI. We had to crop from both left & right side in view for different devices to make it responsive. Before designing banner keep these points in mind.') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Images & links -->
                                    <div class="todays-bottom-slider-target">
                                        @php
                                            $todays_deal_bottom_images = get_setting(
                                                'todays_deal_bottom_images',
                                                null,
                                                $lang,
                                            );
                                            $todays_deal_bottom_links = get_setting(
                                                'todays_deal_bottom_links',
                                                null,
                                                $lang,
                                            );
                                        @endphp
                                        @if ($todays_deal_bottom_images != null)
                                            @foreach (json_decode($todays_deal_bottom_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden"
                                                                        name="todays_deal_bottom_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($todays_deal_bottom_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://"
                                                                    name="todays_deal_bottom_links[]"
                                                                    value="{{ isset(json_decode($todays_deal_bottom_links, true)[$key]) ? json_decode($todays_deal_bottom_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="todays_deal_bottom_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0">
															<input type="text" class="form-control" placeholder="http://" name="todays_deal_bottom_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".todays-bottom-slider-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Flash Deals -->
                    <div class="tab-pane fade" id="flash_deals" role="tabpanel" aria-labelledby="flash-deals-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="flash_deals">
                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="row gutters-16">
                                    <!-- Flash Deal Settings -->
                                    <div class="col-lg-6">
                                        <div class="p-4 border h-250px h-lg-300px" style="background: #fcfcfc;">
                                            <p class="fs-14 fw-500 mb-3">{{ translate('Flash Deal Section Settings') }}
                                            </p>
                                            <!-- Background color -->
                                            <div class="form-group">
                                                <label
                                                    class="col-from-label fs-13 fw-500">{{ translate('Background color') }}</label>
                                                <div class="input-group mb-3">
                                                    <input type="hidden" name="types[]" value="flash_deal_bg_color">
                                                    <input type="text" class="form-control aiz-color-input"
                                                        placeholder="#000000" name="flash_deal_bg_color"
                                                        value="{{ get_setting('flash_deal_bg_color') }}">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text p-0">
                                                            <input class="aiz-color-picker border-0 size-40px"
                                                                type="color"
                                                                value="{{ get_setting('flash_deal_bg_color') }}">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- background color checkbox -->
                                            <div class="form-group d-inline-block">
                                                <label class="aiz-checkbox">
                                                    <input type="hidden" name="types[]"
                                                        value="flash_deal_bg_full_width">
                                                    <input type="checkbox" class="check-one"
                                                        name="flash_deal_bg_full_width" value="1"
                                                        @if (get_setting('flash_deal_bg_full_width') == 1) checked @endif>
                                                    <span
                                                        class="fs-13 fw-400">{{ translate('Use background color as full width') }}</span>
                                                    <span class="aiz-square-check"></span>
                                                </label>
                                            </div>

                                            <!-- Banner Text Color -->
                                            <div class="form-group">
                                                <label
                                                    class="col-from-label fs-13 fw-500">{{ translate('Flash Deal Banner Text Color') }}</label>
                                                <div class="input-group mb-3 d-flex">
                                                    @php
                                                        $flash_deal_banner_text_color = get_setting(
                                                            'flash_deal_banner_menu_text',
                                                        );
                                                    @endphp
                                                    <input type="hidden" name="types[]"
                                                        value="flash_deal_banner_menu_text">
                                                    <div class="radio mar-btm mr-3 d-flex align-items-center">
                                                        <input id="flash_deal_banner_menu_text_light"
                                                            class="magic-radio" type="radio"
                                                            name="flash_deal_banner_menu_text" value="light"
                                                            @if ($flash_deal_banner_text_color == 'light' || $flash_deal_banner_text_color == null) checked @endif>
                                                        <label for="flash_deal_banner_menu_text_light"
                                                            class="mb-0 ml-2">{{ translate('Light') }}</label>
                                                    </div>
                                                    <div class="radio mar-btm mr-3 d-flex align-items-center">
                                                        <input id="flash_deal_banner_menu_text_dark" class="magic-radio"
                                                            type="radio" name="flash_deal_banner_menu_text"
                                                            value="dark"
                                                            @if ($flash_deal_banner_text_color == 'dark') checked @endif>
                                                        <label for="flash_deal_banner_menu_text_dark"
                                                            class="mb-0 ml-2">{{ translate('Dark') }}</label>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Today's Deal -->
                    <div class="tab-pane fade" id="todays_deal" role="tabpanel" aria-labelledby="todays-deal-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="todays_deal">
                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="row gutters-16">
                                    <!-- Todays Deal Section Settings -->
                                    <div class="col-lg-5 order-lg-1 mb-3">
                                        <div class="p-4 border h-250px h-lg-300px" style="background: #fcfcfc;">
                                            <p class="fs-14 fw-500">{{ translate("Today's Deal Section Settings") }}</p>
                                            <!-- background color checkbox -->
                                            <div class="form-group d-inline-block">
                                                <label class="aiz-checkbox">
                                                    <input type="hidden" name="types[]"
                                                        value="todays_deal_section_bg">
                                                    <input type="checkbox" class="check-one"
                                                        name="todays_deal_section_bg" value="1"
                                                        @if (get_setting('todays_deal_section_bg') == 1) checked @endif>
                                                    <span
                                                        class="fs-13 fw-400">{{ translate('Use background color in this section') }}</span>
                                                    <span class="aiz-square-check"></span>
                                                </label>
                                            </div>
                                            <!-- Select Color -->
                                            <div class="form-group ml-4">
                                                <label class="col-from-label">{{ translate('Select Color') }}</label>
                                                <div class="input-group mb-3">
                                                    <input type="hidden" name="types[]"
                                                        value="todays_deal_section_bg_color">
                                                    <input type="text" class="form-control aiz-color-input"
                                                        placeholder="#000000" name="todays_deal_section_bg_color"
                                                        value="{{ get_setting('todays_deal_section_bg_color') }}">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text p-0">
                                                            <input class="aiz-color-picker border-0 size-40px"
                                                                type="color"
                                                                value="{{ get_setting('todays_deal_section_bg_color') }}">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Todays Deal Settings -->
                                    <div class="col-lg-7">
                                        <div class="w-100">
                                            <!-- Large Banner -->
                                            <div class="form-group">
                                                <label
                                                    class="col-from-label fs-13 fw-500">{{ translate('Large Banner') }}
                                                    (<small>{{ translate('Will be shown in large device') }}</small>)</label>
                                                <div class="input-group " data-toggle="aizuploader" data-type="image">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text bg-soft-secondary">
                                                            {{ translate('Browse') }}</div>
                                                    </div>
                                                    <div class="form-control file-amount">{{ translate('Choose File') }}
                                                    </div>
                                                    <input type="hidden" name="types[][{{ $lang }}]"
                                                        value="todays_deal_banner">
                                                    <input type="hidden" name="todays_deal_banner"
                                                        value="{{ get_setting('todays_deal_banner', null, $lang) }}"
                                                        class="selected-files">
                                                </div>
                                                <div class="file-preview box"></div>
                                            </div>

                                            <!-- Small Banner -->
                                            <div class="form-group">
                                                <label
                                                    class="col-from-label fs-13 fw-500">{{ translate('Small Banner') }}
                                                    (<small>{{ translate('Will be shown in small device') }}</small>)</label>
                                                <div class="input-group " data-toggle="aizuploader" data-type="image">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text bg-soft-secondary">
                                                            {{ translate('Browse') }}</div>
                                                    </div>
                                                    <div class="form-control file-amount">{{ translate('Choose File') }}
                                                    </div>
                                                    <input type="hidden" name="types[][{{ $lang }}]"
                                                        value="todays_deal_banner_small">
                                                    <input type="hidden" name="todays_deal_banner_small"
                                                        value="{{ get_setting('todays_deal_banner_small', null, $lang) }}"
                                                        class="selected-files">
                                                </div>
                                                <div class="file-preview box"></div>
                                            </div>

                                            <!-- Products background color -->
                                            <div class="form-group">
                                                <label
                                                    class="col-from-label fs-13 fw-500">{{ translate('Products background color') }}</label>
                                                <div class="input-group">
                                                    @php $todays_deal_bg_color =  get_setting('todays_deal_bg_color') @endphp
                                                    <input type="hidden" name="types[]" value="todays_deal_bg_color">
                                                    <input type="text" class="form-control aiz-color-input"
                                                        placeholder="#000000" name="todays_deal_bg_color"
                                                        value="{{ $todays_deal_bg_color }}">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text p-0">
                                                            <input class="aiz-color-picker border-0 size-40px"
                                                                type="color" value="{{ $todays_deal_bg_color }}">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Banner Text Color -->
                                            <div class="form-group">
                                                <label
                                                    class="col-from-label fs-13 fw-500">{{ translate("Today's Deal Banner Text Color") }}</label>
                                                <div class="input-group mb-3 d-flex">
                                                    @php
                                                        $todays_deal_banner_text_color = get_setting(
                                                            'todays_deal_banner_text_color',
                                                        );
                                                    @endphp
                                                    <input type="hidden" name="types[]"
                                                        value="todays_deal_banner_text_color">
                                                    <div class="radio mar-btm mr-3 d-flex align-items-center">
                                                        <input id="todays_deal_banner_text_light" class="magic-radio"
                                                            type="radio" name="todays_deal_banner_text_color"
                                                            value="light"
                                                            @if ($todays_deal_banner_text_color == 'light' || $todays_deal_banner_text_color == null) checked @endif>
                                                        <label for="todays_deal_banner_text_light"
                                                            class="mb-0 ml-2">{{ translate('Light') }}</label>
                                                    </div>
                                                    <div class="radio mar-btm mr-3 d-flex align-items-center">
                                                        <input id="todays_deal_banner_text_dark" class="magic-radio"
                                                            type="radio" name="todays_deal_banner_text_color"
                                                            value="dark"
                                                            @if ($todays_deal_banner_text_color == 'dark') checked @endif>
                                                        <label for="todays_deal_banner_text_dark"
                                                            class="mb-0 ml-2">{{ translate('Dark') }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Banner Level 1 -->
                    <div class="tab-pane fade" id="banner_1" role="tabpanel" aria-labelledby="banner-1-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="banner_1">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_banner1_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_banner1_links">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <label
                                        class="col-from-label fs-13 fw-500 mb-3">{{ translate('Banner & Links (Max 3)') }}</label>

                                    <!-- Images & links -->
                                    <div class="home-banner1-target">
                                        @php
                                            $home_banner1_images = get_setting('home_banner1_images', null, $lang);
                                            $home_banner1_links = get_setting('home_banner1_links', null, $lang);
                                        @endphp
                                        @if ($home_banner1_images != null)
                                            @foreach (json_decode($home_banner1_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden" name="home_banner1_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($home_banner1_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://" name="home_banner1_links[]"
                                                                    value="{{ isset(json_decode($home_banner1_links, true)[$key]) ? json_decode($home_banner1_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="home_banner1_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0 mb-0">
															<input type="text" class="form-control" placeholder="http://" name="home_banner1_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".home-banner1-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Banner Level 2 -->
                    <div class="tab-pane fade" id="banner_2" role="tabpanel" aria-labelledby="banner-2-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="banner_2">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_banner2_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_banner2_links">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <label
                                        class="col-from-label fs-13 fw-500 mb-3">{{ translate('Banner & Links (Max 3)') }}</label>

                                    <!-- Images & links -->
                                    <div class="home-banner2-target">
                                        @php
                                            $home_banner2_images = get_setting('home_banner2_images', null, $lang);
                                            $home_banner2_links = get_setting('home_banner2_links', null, $lang);
                                        @endphp
                                        @if ($home_banner2_images != null)
                                            @foreach (json_decode($home_banner2_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden" name="home_banner2_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($home_banner2_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://" name="home_banner2_links[]"
                                                                    value="{{ isset(json_decode($home_banner2_links, true)[$key]) ? json_decode($home_banner2_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="home_banner2_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0 mb-0">
															<input type="text" class="form-control" placeholder="http://" name="home_banner2_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".home-banner2-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Banner Level 3 -->
                    <div class="tab-pane fade" id="banner_3" role="tabpanel" aria-labelledby="banner-3-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="banner_3">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_banner3_images">
                            <input type="hidden" name="types[][{{ $lang }}]" value="home_banner3_links">

                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <label
                                        class="col-from-label fs-13 fw-500 mb-3">{{ translate('Banner & Links (Max 3)') }}</label>

                                    <!-- Images & links -->
                                    <div class="home-banner3-target">
                                        @php
                                            $home_banner3_images = get_setting('home_banner3_images', null, $lang);
                                            $home_banner3_links = get_setting('home_banner3_links', null, $lang);
                                        @endphp
                                        @if ($home_banner3_images != null)
                                            @foreach (json_decode($home_banner3_images, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <!-- Image -->
                                                        <div class="col-md-5">
                                                            <div class="form-group mb-md-0">
                                                                <div class="input-group" data-toggle="aizuploader"
                                                                    data-type="image">
                                                                    <div class="input-group-prepend">
                                                                        <div
                                                                            class="input-group-text bg-soft-secondary font-weight-medium">
                                                                            {{ translate('Browse') }}</div>
                                                                    </div>
                                                                    <div class="form-control file-amount">
                                                                        {{ translate('Choose File') }}</div>
                                                                    <input type="hidden" name="home_banner3_images[]"
                                                                        class="selected-files"
                                                                        value="{{ json_decode($home_banner3_images, true)[$key] }}">
                                                                </div>
                                                                <div class="file-preview box sm">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- link -->
                                                        <div class="col-md">
                                                            <div class="form-group mb-md-0">
                                                                <input type="text" class="form-control"
                                                                    placeholder="http://" name="home_banner3_links[]"
                                                                    value="{{ isset(json_decode($home_banner3_links, true)[$key]) ? json_decode($home_banner3_links, true)[$key] : '' }}">
                                                            </div>
                                                        </div>
                                                        <!-- remove parent button -->
                                                        <div class="col-md-auto">
                                                            <div class="form-group mb-md-0">
                                                                <button type="button"
                                                                    class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                    data-toggle="remove-parent"
                                                                    data-parent=".remove-parent">
                                                                    <i class="las la-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<!-- Image -->
													<div class="col-md-5">
														<div class="form-group mb-md-0">
															<div class="input-group" data-toggle="aizuploader" data-type="image">
																<div class="input-group-prepend">
																	<div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
																</div>
																<div class="form-control file-amount">{{ translate('Choose File') }}</div>
																<input type="hidden" name="home_banner3_images[]" class="selected-files" value="">
															</div>
															<div class="file-preview box sm">
															</div>
														</div>
													</div>
													<!-- link -->
													<div class="col-md">
														<div class="form-group mb-md-0 mb-0">
															<input type="text" class="form-control" placeholder="http://" name="home_banner3_links[]" value="">
														</div>
													</div>
													<!-- remove parent button -->
													<div class="col-md-auto">
														<div class="form-group mb-md-0">
															<button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger" data-toggle="remove-parent" data-parent=".remove-parent">
																<i class="las la-times"></i>
															</button>
														</div>
													</div>
												</div>
											</div>'
                                            data-target=".home-banner3-target">
                                            <i class="las la-2x text-success la-plus-circle"></i>
                                            <span class="ml-2">{{ translate('Add New') }}</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Save Button -->
                                <div class="mt-4 text-right">
                                    <button type="submit"
                                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if (addon_is_activated('auction'))
                        <!-- Auction Banner -->
                        <div class="tab-pane fade" id="auction" role="tabpanel" aria-labelledby="auction-tab">
                            <form action="{{ route('business_settings.update') }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="tab" value="auction">
                                <div class="bg-white p-3 p-sm-2rem">
                                    <div class="w-100">
                                        <label
                                            class="col-from-label fs-13 fw-500 mb-3">{{ translate('Auction Banner') }}</label>
                                        <!-- Images -->
                                        <div class="form-group">
                                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text bg-soft-secondary font-weight-medium">
                                                        {{ translate('Browse') }}</div>
                                                </div>
                                                <div class="form-control file-amount">{{ translate('Choose File') }}
                                                </div>
                                                <input type="hidden" name="types[][{{ $lang }}]"
                                                    value="auction_banner_image">
                                                <input type="hidden" name="auction_banner_image"
                                                    class="selected-files"
                                                    value="{{ get_setting('auction_banner_image', null, $lang) }}">
                                            </div>
                                            <div class="file-preview box sm">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Save Button -->
                                    <div class="mt-4 text-right">
                                        <button type="submit"
                                            class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if (get_setting('coupon_system') == 1)
                        <!-- Coupon system -->
                        <div class="tab-pane fade" id="coupon" role="tabpanel" aria-labelledby="coupon-tab">
                            <form action="{{ route('business_settings.update') }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="tab" value="coupon">
                                <div class="bg-white p-3 p-sm-2rem">
                                    <div class="w-100">
                                        <div class="row gutters-16">

                                            <!-- Background Image -->
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label
                                                        class="col-from-label fs-13 fw-500">{{ translate('Background Image') }}</label>
                                                    <div class="input-group mb-3">
                                                        <div class="input-group" data-toggle="aizuploader"
                                                            data-type="image">
                                                            <div class="input-group-prepend">
                                                                <div
                                                                    class="input-group-text bg-soft-secondary font-weight-medium">
                                                                    {{ translate('Browse') }}</div>
                                                            </div>
                                                            <div class="form-control file-amount">
                                                                {{ translate('Choose File') }}</div>
                                                            <input type="hidden" name="types[][{{ $lang }}]"
                                                                value="coupon_background_image">
                                                            <input type="hidden" name="coupon_background_image"
                                                                class="selected-files"
                                                                value="{{ get_setting('coupon_background_image', null, $lang) }}">
                                                        </div>
                                                        <div class="file-preview box sm">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Background Color -->
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label
                                                        class="col-from-label fs-13 fw-500">{{ translate('Background color') }}</label>
                                                    <div class="input-group mb-3">
                                                        @php $coupon_background_color = get_setting('cupon_background_color') @endphp
                                                        <input type="hidden" name="types[]"
                                                            value="cupon_background_color">
                                                        <input type="text" class="form-control aiz-color-input"
                                                            placeholder="#000000" name="cupon_background_color"
                                                            value="{{ $coupon_background_color }}">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text p-0">
                                                                <input class="aiz-color-picker border-0 size-40px"
                                                                    type="color"
                                                                    value="{{ $coupon_background_color }}">
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Title -->
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label
                                                        class="col-from-label fs-13 fw-500">{{ translate('Title') }}</label>
                                                    <input type="hidden" name="types[][{{ $lang }}]"
                                                        value="cupon_title">
                                                    <input type="text" class="form-control"
                                                        placeholder="{{ translate('Title') }}" name="cupon_title"
                                                        value="{{ get_setting('cupon_title', null, $lang) }}">
                                                </div>
                                            </div>
                                            <!-- Subtitle -->
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label
                                                        class="col-from-label fs-13 fw-500">{{ translate('Subtitle') }}</label>
                                                    <input type="hidden" name="types[][{{ $lang }}]"
                                                        value="cupon_subtitle">
                                                    <input type="text" class="form-control"
                                                        placeholder="{{ translate('Subtitle') }}"
                                                        name="cupon_subtitle"
                                                        value="{{ get_setting('cupon_subtitle', null, $lang) }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Save Button -->
                                    <div class="mt-4 text-right">
                                        <button type="submit"
                                            class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endif

                    <!-- Category Wise Products -->
                    <div class="tab-pane fade" id="home_categories" role="tabpanel"
                        aria-labelledby="home-categories-tab">
                        <form action="{{ route('business_settings.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tab" value="home_categories">
                            <div class="bg-white p-3 p-sm-2rem">
                                <div class="w-100">
                                    <label
                                        class="col-from-label fs-13 fw-500 mb-3">{{ translate('Categories') }}</label>
                                    <div class="home-categories-target">
                                        <input type="hidden" name="types[]" value="home_categories">
                                        @php $home_categories = get_setting('home_categories'); @endphp
                                        @if ($home_categories != null)
                                            @php $categories = \App\Models\Category::where('parent_id', 0)->with('childrenCategories')->get(); @endphp
                                            @foreach (json_decode($home_categories, true) as $key => $value)
                                                <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent"
                                                    style="border: 1px dashed #e4e5eb;">
                                                    <div class="row gutters-5">
                                                        <div class="col">
                                                            <div class="form-group mb-0">
                                                                <select class="form-control aiz-selectpicker"
                                                                    name="home_categories[]" data-live-search="true"
                                                                    data-selected={{ $value }} required>
                                                                    @foreach ($categories as $category)
                                                                        <option value="{{ $category->id }}">
                                                                            {{ $category->getTranslation('name') }}
                                                                        </option>
                                                                        @foreach ($category->childrenCategories as $childCategory)
                                                                            @include(
                                                                                'categories.child_category',
                                                                                [
                                                                                    'child_category' => $childCategory,
                                                                                ]
                                                                            )
                                                                        @endforeach
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto">
                                                            <button type="button"
                                                                class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                                                data-toggle="remove-parent"
                                                                data-parent=".remove-parent">
                                                                <i class="las la-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Add button -->
                                    <div class="">
                                        <button type="button"
                                            class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                            style="background: #fcfcfc;" data-toggle="add-more"
                                            data-content='
											<div class="p-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
												<div class="row gutters-5">
													<div class="col">
														<div class="form-group mb-0">
															<select class="form-control aiz-selectpicker" name="home_categories[]" data-live-search="true" required>
																@foreach (\App\Models\Category::where('parent_id',
                                            0)->with('childrenCategories')->get() as $category)
                                            <option value="{{ $category->id }}">
                                                {{ $category->getTranslation('name') }}</option>
                                            @foreach ($category->childrenCategories as $childCategory)
                                                @include('categories.child_category', [
                                                    'child_category' => $childCategory,
                                                ])
                                            @endforeach
                                            @endforeach
                                            </select>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger"
                                        data-toggle="remove-parent" data-parent=".remove-parent">
                                        <i class="las la-times"></i>
                                    </button>
                                </div>
                            </div>
                    </div>'
                    data-target=".home-categories-target">
                    <i class="las la-2x text-success la-plus-circle"></i>
                    <span class="ml-2">{{ translate('Add New') }}</span>
                    </button>
                </div>
            </div>
            <!-- Save Button -->
            <div class="mt-4 text-right">
                <button type="submit"
                    class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
            </div>
        </div>
        </form>
    </div>

    <!-- Classifieds -->
    <div class="tab-pane fade" id="classifieds" role="tabpanel" aria-labelledby="classifieds-tab">
        <form action="{{ route('business_settings.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="tab" value="classifieds">
            <div class="bg-white p-3 p-sm-2rem">
                <div class="row">
                    <!-- Large Banner -->
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="col-from-label fs-13 fw-500">{{ translate('Large Banner') }}
                                (<small>{{ translate('Will be shown in large device') }}</small>)</label>
                            <div class="input-group " data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary">{{ translate('Browse') }}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="types[][{{ $lang }}]"
                                    value="classified_banner_image">
                                <input type="hidden" name="classified_banner_image"
                                    value="{{ get_setting('classified_banner_image', null, $lang) }}"
                                    class="selected-files">
                            </div>
                            <div class="file-preview box"></div>
                        </div>
                    </div>
                    <!-- Small Banner -->
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="col-from-label fs-13 fw-500">{{ translate('Small Banner') }}
                                (<small>{{ translate('Will be shown in small device') }}</small>)</label>
                            <div class="input-group " data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary">{{ translate('Browse') }}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="types[][{{ $lang }}]"
                                    value="classified_banner_image_small">
                                <input type="hidden" name="classified_banner_image_small"
                                    value="{{ get_setting('classified_banner_image_small', null, $lang) }}"
                                    class="selected-files">
                            </div>
                            <div class="file-preview box"></div>
                        </div>
                    </div>
                </div>
                <!-- Save Button -->
                <div class="mt-4 text-right">
                    <button type="submit"
                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Top Brands -->
    <div class="tab-pane fade" id="brands" role="tabpanel" aria-labelledby="brands-tab">
        <form action="{{ route('business_settings.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="tab" value="brands">
            <div class="bg-white p-3 p-sm-2rem">
                <div class="w-100">
                    <label class="col-from-label fs-13 fw-500 mb-3">{{ translate('Top Brands (Max 12)') }}</label>
                    <!-- Brands -->
                    <div class="form-group">
                        <input type="hidden" name="types[]" value="top_brands">
                        <select name="top_brands[]" class="form-control aiz-selectpicker" multiple
                            data-max-options="12" data-live-search="true"
                            data-selected="{{ get_setting('top_brands') }}">
                            @foreach (\App\Models\Brand::all() as $key => $brand)
                                <option value="{{ $brand->id }}">{{ $brand->getTranslation('name') }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <!-- Save Button -->
                <div class="mt-4 text-right">
                    <button type="submit"
                        class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Save') }}</button>
                </div>
            </div>
        </form>
    </div>

    </div>
    </div>
    </div>
    </div>

@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            AIZ.plugins.bootstrapSelect('refresh');
        });
    </script>
    <script>
        $(document).ready(function() {
            var hash = document.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
            } else {
                $('.nav-tabs a[href="#home_slider"]').tab('show');
            }

            // Change hash for page-reload
            $('.nav-tabs a').on('shown.bs.tab', function(e) {
                window.location.hash = e.target.hash;
            });
        });
    </script>
@endsection
