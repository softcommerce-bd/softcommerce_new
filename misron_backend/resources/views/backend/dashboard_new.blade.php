@extends('backend.layouts.app')

@section('content')

    {{-- Right: large section (chart, table, etc.) --}}
    @include('backend.dashboard-style')
    @if (auth()->user()->can('smtp_settings') && env('MAIL_USERNAME') == null && env('MAIL_PASSWORD') == null)
        <div class="">
            <div class="alert alert-info d-flex align-items-center">
                {{ translate('Please Configure SMTP Setting to work all email sending functionality') }},
                <a class="alert-link ml-2" href="{{ route('smtp_settings.index') }}">{{ translate('Configure Now') }}</a>
            </div>
        </div>
    @endif

    @can('admin_dashboard')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <div class="dashboard-container">

            {{-- FIRST ROW --}}
            <div class="row g-1 pb-2">
                {{-- Left: 3 stacked boxes (merged visually) --}}
                <div class="col-12 col-sm-6 col-md-2 col-lg-3 col-xl-3 col-xxl-2 d-flex my-3 px-2">
                    <div class="dashboard-stack d-flex flex-column w-100 h-100">
                        <div class="dashboard-stack-box flex-fill mb-3">
                            <h3 class="executive-label fw-500 text-white mb-0">Total Revenue</h3>
                            <h1 class="executive-price fw-500 text-white mb-1 py-1">{{ single_price($sale_this_month) }}</h1>
                            <span class="sale-indicator">
                                <strong class="sale-percent px-1">
                                    @if ($sale_percent >= 0)
                                        <div class="text-green">
                                            <i class="fas fa-arrow-up me-1"></i> {{ abs($sale_percent) }}%
                                        </div>
                                    @else
                                        <div class="text-danger">
                                            <i class="fas fa-arrow-down me-1"></i> {{ abs($sale_percent) }}%
                                        </div>
                                    @endif
                                </strong>
                                <span class="sale-text">from last month</span>
                            </span>
                        </div>
                        <div class="dashboard-stack-box flex-fill mb-3">
                            <div class="pt-3">
                                <h3 class="executive-label text-white mb-0">Total Orders</h3>
                                <h1 class="executive-price text-white mb-1 py-1">{{ $order_this_month }}</h1>
                                <span class="sale-indicator">
                                    <strong class="sale-percent px-1">
                                        @if ($order_percent >= 0)
                                            <div class="text-green">
                                                <i class="fas fa-arrow-up me-1"></i> {{ abs($order_percent) }}%
                                            </div>
                                        @else
                                            <div class="text-danger">
                                                <i class="fas fa-arrow-down me-1"></i> {{ abs($order_percent) }}%
                                            </div>
                                        @endif
                                    </strong>
                                    <span class="sale-text">from last month</span>
                                </span>
                            </div>
                        </div>
                        <div class="dashboard-stack-box flex-fill">
                            <div class="pt-3">
                                <h3 class="executive-label text-white mb-0">Average Orders</h3>
                                <h1 class="executive-price text-white mb-1 py-1">{{ single_price($avg_order_this_month) }}</h1>
                                <span class="sale-indicator">
                                    <strong class="sale-percent px-1">
                                        @if ($avg_order_percent >= 0)
                                            <div class="text-green">
                                                <i class="fas fa-arrow-up me-1"></i> {{ abs($avg_order_percent) }}%
                                            </div>
                                        @else
                                            <div class="text-danger">
                                                <i class="fas fa-arrow-down me-1"></i> {{ abs($avg_order_percent) }}%
                                            </div>
                                        @endif
                                    </strong>
                                    <span class="sale-text">from last month</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-2 col-lg-3 col-xl-3 col-xxl-2 d-flex my-3 px-2">
                    <div class="dashboard-single-box courier-status-card bg-white shadow-sm w-100 h-100 border d-flex flex-column"
                        style="border:1px solid #F6F8FA;">

                        <h3 class="fs-15 fw-500 text-start">Courier Status Report</h3>

                        <!-- COURIER SELECT HEADER -->
                        <div class="courier-select-header d-flex align-items-center mb-3" onclick="toggleCourierDropdown()">
                            <img id="courierLogo" src="{{ static_asset('/assets/img/steadfast.png') }}" alt="Courier Logo"
                                class="me-2 courier-logo" style="width:28px;">
                            <span id="courierName" class="fs-16 fw-bold flex-grow-1 text-start">Steadfast Courier</span>

                            <span class="chevron-icon">
                                <svg id="chevronIcon" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                    fill="currentColor" class="bi bi-chevron-down" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                        d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z" />
                                </svg>
                            </span>
                        </div>

                        <!-- DROPDOWN OPTIONS -->
                        <div id="courierDropdown" class="courier-dropdown-content mb-3">
                            <div class="p-2 border-bottom" onclick="selectCourier('pathao')" style="cursor:pointer;">
                                <img src="{{ static_asset('/assets/img/pathao.png') }}" width="22"> Pathao
                            </div>
                            <div class="p-2" onclick="selectCourier('steadfast')" style="cursor:pointer;">
                                <img src="{{ static_asset('/assets/img/steadfast.png') }}" width="22"> Steadfast
                            </div>
                        </div>

                        <!-- STEADFAST DATA -->
                        <div id="steadfastData" class="courier-dropdown-content active">
                            <div class="courier-status-item text-start">
                                <p class="status-title">Total Order:</p>
                                <div class="d-flex justify-content-between pr-4">
                                    <p class="status-value fw-bold">{{ $steadfast_stats['total_orders'] }}</p>
                                    <span
                                        class="text-{{ $steadfast_stats['order_percent'] >= 0 ? 'success' : 'danger' }} fw-bold small mb-0">
                                        {{ $steadfast_stats['order_percent'] >= 0 ? '↑' : '↓' }}
                                        {{ abs($steadfast_stats['order_percent']) }}%
                                    </span>
                                </div>
                            </div>

                            <div class="courier-status-item text-start">
                                <p class="status-title">Canceled & Partial:</p>
                                <div class="d-flex justify-content-between pr-4">
                                    <p class="status-value fw-bold" style="color: red;">
                                        {{ $steadfast_stats['cancelled_orders'] }}</p>
                                    <span class="text-danger fw-bold small">↓</span>
                                </div>
                            </div>

                            {{--  <div class="row g-0 courier-split-status mb-3 flex-grow-1 align-items-center">
                                <div class="col-6 text-start flex-column">
                                    <p class="status-title fw-bold mb-1 text-danger">Canceled & Partial</p>
                                    <p class="status-value-small pb-1">Orders:</p>
                                    <p class="status-value fw-bold mb-0">{{ $steadfast_stats['cancelled_orders'] }}</p>
                                </div>
                                <div class="col-6 text-start flex-column">
                                    <p class="status-title fw-bold mb-1">In review</p>
                                    <p class="status-value-small pb-1">Orders:</p>
                                    <p class="status-value fw-bold mb-0">{{ $steadfast_stats['delivered_orders'] }}</p>
                                </div>
                            </div>  --}}

                            <div class="row g-0 courier-split-status mb-3 flex-grow-1 align-items-center">
                                <div class="col-6 text-start flex-column">
                                    <p class="status-title fw-bold mb-1">Pending</p>
                                    <p class="status-value-small pb-1">Orders:</p>
                                    <p class="status-value fw-bold mb-0">{{ $steadfast_stats['pending_orders'] }}</p>
                                </div>
                                <div class="col-6 text-start flex-column">
                                    <p class="status-title fw-bold mb-1">Delivered</p>
                                    <p class="status-value-small pb-1">Orders:</p>
                                    <p class="status-value fw-bold mb-0">{{ $steadfast_stats['delivered_orders'] }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- PATHAO DATA (DEFAULT OPEN) -->
                        <div id="pathaoData" class="courier-dropdown-content">
                            <div class="courier-status-item text-start">
                                <p class="status-title">Total Order:</p>
                                <div class="d-flex justify-content-between pr-4">
                                    <p class="status-value fw-bold">{{ $pathao_stats['total_orders'] }}</p>
                                    <span
                                        class="text-{{ $pathao_stats['order_percent'] >= 0 ? 'success' : 'danger' }} fw-bold small mb-0">
                                        {{ $pathao_stats['order_percent'] >= 0 ? '↑' : '↓' }}
                                        {{ abs($pathao_stats['order_percent']) }}%
                                    </span>
                                </div>
                            </div>

                            <div class="courier-status-item text-start">
                                <p class="status-title">Canceled Order:</p>
                                <div class="d-flex justify-content-between pr-4">
                                    <p class="status-value fw-bold" style="color: red;">{{ $pathao_stats['cancelled_orders'] }}
                                    </p>
                                    <span class="text-danger fw-bold small">↓</span>
                                </div>
                            </div>

                            <div class="row g-0 courier-split-status mb-3 flex-grow-1 align-items-center">
                                <div class="col-6 text-start flex-column">
                                    <p class="status-title fw-bold mb-1">Pending</p>
                                    <p class="status-value-small pb-1">Orders:</p>
                                    <p class="status-value fw-bold mb-0">{{ $pathao_stats['pending_orders'] }}</p>
                                </div>
                                <div class="col-6 text-start flex-column">
                                    <p class="status-title fw-bold mb-1">Delivered</p>
                                    <p class="status-value-small pb-1">Orders:</p>
                                    <p class="status-value fw-bold mb-0">{{ $pathao_stats['delivered_orders'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto text-start pt-2 border-top">
                            <a href="#" class="fs-14 view-all-link text-decoration-none fw-bold small">
                                View All Courier Status →
                            </a>
                        </div>
                    </div>
                </div>


                <script>

                    function toggleCourierDropdown() {
                        const dropdown = document.getElementById('courierDropdown');
                        const chevronIcon = document.getElementById('chevronIcon');

                        if (dropdown.style.display === 'block') {
                            dropdown.style.display = 'none';
                            chevronIcon.classList.remove('bi-chevron-up');
                            chevronIcon.classList.add('bi-chevron-down');
                        } else {
                            dropdown.style.display = 'block';
                            chevronIcon.classList.remove('bi-chevron-down');
                            chevronIcon.classList.add('bi-chevron-up');
                        }
                    }

                    function selectCourier(courier) {
                        const pathaoData = document.getElementById('pathaoData');
                        const steadfastData = document.getElementById('steadfastData');
                        const courierLogo = document.getElementById('courierLogo');
                        const courierName = document.getElementById('courierName');
                        const dropdown = document.getElementById('courierDropdown');
                        const chevronIcon = document.getElementById('chevronIcon');

                        // Hide dropdown
                        dropdown.style.display = 'none';
                        chevronIcon.classList.remove('bi-chevron-up');
                        chevronIcon.classList.add('bi-chevron-down');

                        if (courier === 'pathao') {
                            pathaoData.classList.add('active');
                            steadfastData.classList.remove('active');
                            courierLogo.src = "{{ static_asset('/assets/img/pathao.png') }}";
                            courierName.textContent = "Pathao Courier";
                        } else {
                            pathaoData.classList.remove('active');
                            steadfastData.classList.add('active');
                            courierLogo.src = "{{ static_asset('/assets/img/steadfast.png') }}";
                            courierName.textContent = "Steadfast Courier";
                        }
                    }

                    // Close dropdown when clicking outside
                    document.addEventListener('click', function(event) {
                        const dropdown = document.getElementById('courierDropdown');
                        const header = document.querySelector('.courier-select-header');

                        if (!header.contains(event.target) && !dropdown.contains(event.target)) {
                            dropdown.style.display = 'none';
                            const chevronIcon = document.getElementById('chevronIcon');
                            chevronIcon.classList.remove('bi-chevron-up');
                            chevronIcon.classList.add('bi-chevron-down');
                        }
                    });
                </script>



                <div class="col-12 col-md-4 col-lg-6 col-xl-6 col-xxl-8 d-flex my-3 px-2">
                    <div class="dashboard-wide-box bg-white w-100 h-100 d-flex flex-column">
                        <div class="row w-100 g-0 align-items-stretch">

                            {{-- Left: Customer Overview --}}
                            <div
                                class="col-4 col-md-6 col-lg-3 customer-overview pl-2 d-flex flex-column justify-content-center">
                                <svg width="32" height="32" viewBox="0 0 32 32" fill="none"
                                    xmlns="http://www.w3.org/2000/svg" class="text-center customer-svg">
                                    <g clip-path="url(#clip0_377_2516)">
                                        <path
                                            d="M16 12.9375C18.9982 12.9375 21.4375 10.4983 21.4375 7.5C21.4375 4.50175 18.9982 2.0625 16 2.0625C13.0017 2.0625 10.5625 4.50175 10.5625 7.5C10.5625 10.4983 13.0017 12.9375 16 12.9375ZM16 3.9375C17.9644 3.9375 19.5625 5.53562 19.5625 7.5C19.5625 9.46438 17.9644 11.0625 16 11.0625C14.0356 11.0625 12.4375 9.46438 12.4375 7.5C12.4375 5.53562 14.0356 3.9375 16 3.9375ZM27 12.9375C28.8954 12.9375 30.4375 11.3954 30.4375 9.5C30.4375 7.60456 28.8954 6.0625 27 6.0625C25.1046 6.0625 23.5625 7.60456 23.5625 9.5C23.5625 11.3954 25.1046 12.9375 27 12.9375ZM27 7.9375C27.8616 7.9375 28.5625 8.63844 28.5625 9.5C28.5625 10.3616 27.8616 11.0625 27 11.0625C26.1384 11.0625 25.4375 10.3616 25.4375 9.5C25.4375 8.63844 26.1384 7.9375 27 7.9375ZM27.7563 15.0625H26.3062C24.5976 15.0625 23.1226 16.072 22.4504 17.522C21.0666 16.0114 19.0789 15.0625 16.8737 15.0625H15.1264C12.9211 15.0625 10.9334 16.0114 9.54963 17.522C8.87744 16.072 7.40244 15.0625 5.69375 15.0625H4.24375C1.90375 15.0625 0 16.9547 0 19.2806V26.1063C0 27.116 0.824625 27.9375 1.83825 27.9375H7.57119C7.66731 29.0562 8.60806 29.9375 9.75138 29.9375H22.2487C23.3919 29.9375 24.3327 29.0562 24.4289 27.9375H30.0963C31.1461 27.9375 32.0001 27.0869 32.0001 26.0413V19.2806C32 16.9547 30.0963 15.0625 27.7563 15.0625ZM1.875 19.2806C1.875 17.9886 2.93762 16.9375 4.24375 16.9375H5.69375C6.99988 16.9375 8.0625 17.9886 8.0625 19.2806V19.9226C7.43206 21.5641 7.5625 22.5478 7.5625 26.0625H1.875V19.2806ZM22.5625 27.7487C22.5624 27.8319 22.5293 27.9117 22.4705 27.9705C22.4117 28.0293 22.3319 28.0624 22.2487 28.0625H9.75131C9.66811 28.0624 9.58835 28.0293 9.52952 27.9705C9.47069 27.9116 9.4376 27.8319 9.4375 27.7487V22.6264C9.4375 19.4895 11.9895 16.9375 15.1263 16.9375H16.8736C20.0105 16.9375 22.5625 19.4895 22.5625 22.6263V27.7487ZM30.125 26.0413C30.125 26.0736 30.4745 26.0625 24.4375 26.0625C24.4375 22.5218 24.5669 21.5614 23.9375 19.9226V19.2806C23.9375 17.9886 25.0001 16.9375 26.3062 16.9375H27.7563C29.0624 16.9375 30.125 17.9886 30.125 19.2806V26.0413ZM5 12.9375C6.89544 12.9375 8.4375 11.3954 8.4375 9.5C8.4375 7.60456 6.89544 6.0625 5 6.0625C3.10456 6.0625 1.5625 7.60456 1.5625 9.5C1.5625 11.3954 3.10456 12.9375 5 12.9375ZM5 7.9375C5.86156 7.9375 6.5625 8.63844 6.5625 9.5C6.5625 10.3616 5.86156 11.0625 5 11.0625C4.13844 11.0625 3.4375 10.3616 3.4375 9.5C3.4375 8.63844 4.13844 7.9375 5 7.9375Z"
                                            fill="#666666" />
                                    </g>
                                    <defs>
                                        <clipPath id="clip0_377_2516">
                                            <rect width="32" height="32" fill="white" />
                                        </clipPath>
                                    </defs>
                                </svg>

                                <div class="mb-3">
                                    <h5 class="total_customers fw-600 mb-2">{{ translate('Total Customers') }}</h5>
                                    <h4 class="customer-price fw-bold mb-0">{{ $total_customers }}</h4>
                                </div>

                                <div>
                                    <h3 class="top_customers fw-600 mb-1">
                                        <span class="badge badge-md badge-dot badge-circle badge-danger mr-1"></span>
                                        {{ translate('Top Customers') }}
                                    </h3>
                                    <div class="symbol-group">
                                        @foreach ($top_customers as $top_customer)
                                            <div class="symbol symbol-size rounded-content overflow-hidden"
                                                title="{{ $top_customer->name }}">
                                                <img src="{{ uploaded_asset($top_customer->avatar_original) }}"
                                                    alt="{{ translate('customer') }}" class="h-100 img-fit lazyload"
                                                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="courier-status-item text-start pt-3">
                                    <p class="status-title pt-1">Cart Abandonment</p>
                                    <div class="d-flex align-items-start justify-content-between pr-sm-2 pr-md-0">
                                        <p class="status-value fw-bold">{{ $customer_analytics['cart_abandonment_rate'] }}%
                                        </p>
                                        <span
                                            class="text-{{ $customer_analytics['cart_abandonment_change'] >= 0 ? 'danger' : 'success' }} fw-bold small mb-0">
                                            {{ $customer_analytics['cart_abandonment_change'] >= 0 ? '↑' : '↓' }}
                                            {{ abs($customer_analytics['cart_abandonment_change']) }}%
                                        </span>
                                    </div>
                                </div>
                                <div class="courier-status-item text-start">
                                    <p class="status-title pt-1">Repeat Purchase</p>
                                    <div class="d-flex align-items-start justify-content-between pr-sm-2 pr-md-0">
                                        <p class="status-value fw-bold">{{ $customer_analytics['repeat_purchase_rate'] }}%</p>
                                        <span
                                            class="text-{{ $customer_analytics['repeat_purchase_change'] >= 0 ? 'success' : 'danger' }} fw-bold small mb-0">
                                            {{ $customer_analytics['repeat_purchase_change'] >= 0 ? '↑' : '↓' }}
                                            {{ abs($customer_analytics['repeat_purchase_change']) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Right: Graph Section --}}
                            <div
                                class="col-8 col-md-6 col-lg-9 customer-graph position-relative d-flex flex-column justify-content-center p-2">
                                <h5 class="fs-15 fw-600 mb-3">Average Customer Lifetime Value</h5>
                                <canvas id="graph-customer" class="w-100"></canvas>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

              {{-- start here All cards section  --}}
                <div class="col-12 col-md-12 px-2">
                    <div class="w-100 d-flex flex-column h-100">
                        <div class="row flex-grow-1">

                            <div class="col-6 col-md-3 col-xxl-2 mb-3 d-flex pr-1">
                                <div class="card shadow-sm h-100 w-100" style="border-radius: 25px;">
                                    <div class=" justify-responsive px-2 pt-4">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="mr-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center svg-bg-size">
                                                <svg width="20" height="20" viewBox="0 0 36 36" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M10.0966 24.4271C9.94688 24.3597 9.78536 24.3225 9.62125 24.3175C9.45714 24.3125 9.29366 24.3399 9.14013 24.3981C8.98661 24.4562 8.84604 24.5441 8.72647 24.6566C8.6069 24.7691 8.51066 24.9041 8.44325 25.0538C8.37584 25.2035 8.33858 25.365 8.33359 25.5291C8.3286 25.6932 8.35598 25.8567 8.41417 26.0103C8.47236 26.1638 8.56022 26.3043 8.67274 26.4239C8.78525 26.5435 8.92021 26.6397 9.06992 26.7071L12.4033 28.2071C12.553 28.2746 12.7145 28.3118 12.8786 28.3168C13.0427 28.3218 13.2062 28.2944 13.3597 28.2362C13.5132 28.178 13.6538 28.0902 13.7734 27.9777C13.8929 27.8651 13.9892 27.7302 14.0566 27.5805C14.124 27.4308 14.1613 27.2692 14.1663 27.1051C14.1712 26.941 14.1439 26.7775 14.0857 26.624C14.0275 26.4705 13.9396 26.3299 13.8271 26.2104C13.7146 26.0908 13.5796 25.9946 13.4299 25.9271L10.0966 24.4271Z"
                                                        fill="white" />
                                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                                        d="M20.9033 0.66045C19.9678 0.225405 18.9484 0 17.9167 0C16.8849 0 15.8656 0.225405 14.93 0.66045L4.09667 5.69878C2.87306 6.26777 1.83743 7.17443 1.11168 8.31208C0.385921 9.44973 0.000231839 10.771 0 12.1204V23.8121C0.000143727 25.1716 0.391513 26.5024 1.12739 27.6455C1.86326 28.7886 2.91255 29.6958 4.15 30.2588L14.9833 35.1888C15.9045 35.6079 16.9047 35.8247 17.9167 35.8247C18.9286 35.8247 19.9289 35.6079 20.85 35.1888L31.6833 30.2588C32.9208 29.6958 33.9701 28.7886 34.7059 27.6455C35.4418 26.5024 35.8332 25.1716 35.8333 23.8121V12.1204C35.8331 10.771 35.4474 9.44973 34.7217 8.31208C33.9959 7.17443 32.9603 6.26777 31.7367 5.69878L20.9033 0.66045ZM15.985 2.92712C16.5904 2.64563 17.2499 2.49979 17.9175 2.49979C18.5851 2.49979 19.2446 2.64563 19.85 2.92712L30.6833 7.96545C31.4617 8.32712 32.1067 8.89212 32.5633 9.57878L27.5967 11.9321L12.4867 4.55378L15.985 2.92712ZM9.56833 5.91045L5.15 7.96712C4.38576 8.3214 3.73407 8.87966 3.26667 9.58045L17.9167 16.5171L24.7067 13.3004L9.56833 5.91045ZM2.5 11.9838L16.6667 18.6938V33.1538C16.4443 33.0908 16.2271 33.0111 16.0167 32.9155L5.18333 27.9855C4.38264 27.6211 3.70372 27.034 3.22762 26.2943C2.75151 25.5546 2.49834 24.6935 2.49833 23.8138V12.1204L2.5 11.9838ZM19.1667 33.1521C19.3879 33.0896 19.604 33.0104 19.8133 32.9155L30.6467 27.9855C31.4474 27.6211 32.1263 27.034 32.6024 26.2943C33.0785 25.5546 33.3317 24.6935 33.3317 23.8138V12.1204L33.33 11.9821L19.165 18.6905L19.1667 33.1521Z"
                                                        fill="white" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-1 info-title">Total Products</h6>
                                                <h3 class="font-weight-bold mb-0 fs-20">{{ $total_products ?? 0 }}</h3>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3 col-xxl-2 mb-3 d-flex pr-1">
                                <div class="card shadow-sm h-100 w-100" style="border-radius: 25px;">
                                    <div class=" justify-responsive px-2 pt-4">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="mr-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center svg-bg-size">
                                                <svg width="20" height="20" viewBox="0 0 27 37" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                                        d="M13.3333 3.33352C10.6812 3.33352 8.13764 4.38709 6.26228 6.26245C4.38691 8.13782 3.33334 10.6814 3.33334 13.3335C3.33334 15.9857 4.38691 18.5292 6.26228 20.4046C8.13764 22.2799 10.6812 23.3335 13.3333 23.3335C15.9855 23.3335 18.529 22.2799 20.4044 20.4046C22.2798 18.5292 23.3333 15.9857 23.3333 13.3335C23.3333 10.6814 22.2798 8.13782 20.4044 6.26245C18.529 4.38709 15.9855 3.33352 13.3333 3.33352ZM9.47699e-06 13.3335C-2.03108e-05 11.1586 0.532003 9.01664 1.54969 7.09448C2.56738 5.17232 4.03983 3.5283 5.83866 2.30576C7.63749 1.08322 9.70807 0.319283 11.8699 0.0805615C14.0317 -0.15816 16.2191 0.13558 18.2413 0.936171C20.2635 1.73676 22.0592 3.01989 23.4718 4.67369C24.8844 6.32748 25.8709 8.30172 26.3455 10.4243C26.82 12.5468 26.7681 14.7532 26.1943 16.8511C25.6204 18.949 24.5421 20.8747 23.0533 22.4602L25.7933 34.6335C25.8658 34.9536 25.8421 35.2879 25.7253 35.5945C25.6085 35.9012 25.4038 36.1665 25.1368 36.3573C24.8699 36.5481 24.5525 36.6558 24.2246 36.667C23.8966 36.6782 23.5727 36.5923 23.2933 36.4202L22.695 36.0518C19.9116 34.3389 16.6953 33.4601 13.4276 33.5195C10.1599 33.5788 6.97771 34.574 4.25834 36.3869C3.98409 36.5701 3.66164 36.6678 3.33182 36.6676C3.00199 36.6675 2.67963 36.5695 2.40553 36.3861C2.13143 36.2026 1.91792 35.942 1.79203 35.6371C1.66615 35.3323 1.63354 34.9969 1.69834 34.6735L4.05168 22.9069C2.76807 21.665 1.74776 20.1773 1.05165 18.5325C0.355538 16.8877 -0.00212268 15.1195 9.47699e-06 13.3335ZM7.01668 25.0785L5.66334 31.8452C8.20214 30.7534 10.936 30.188 13.6996 30.1831C16.4632 30.1783 19.199 30.734 21.7417 31.8169L20.16 24.7885C18.0964 26.021 15.737 26.6702 13.3333 26.6669C11.1286 26.67 8.95774 26.1241 7.01668 25.0785Z"
                                                        fill="white" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-1 info-title">Today Incomplete Orders</h6>
                                                <h3 class="font-weight-bold mb-0 fs-20">{{ $today_incomplete_orders ?? 0 }}</h3>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3 col-xxl-2 mb-3 d-flex pr-1">
                                <div class="card shadow-sm h-100 w-100" style="border-radius: 25px;">
                                    <div class=" justify-responsive px-2 pt-4">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="mr-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center svg-bg-size">
                                                <svg width="20" height="20" viewBox="0 0 30 30" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M26.65 30H19.85C18 30 16.5 28.5 16.5 26.65V17.5C16.5 16.95 16.95 16.5 17.5 16.5H26.65C28.5 16.5 30 18 30 19.85V26.65C30 28.5 28.5 30 26.65 30ZM18.5 18.5V26.65C18.5 27.4 19.1 28 19.85 28H26.65C27.4 28 28 27.4 28 26.65V19.85C28 19.1 27.4 18.5 26.65 18.5H18.5ZM10.15 30H3.35C1.5 30 0 28.5 0 26.65V19.85C0 18 1.5 16.5 3.35 16.5H12.5C13.05 16.5 13.5 16.95 13.5 17.5V26.65C13.5 28.5 12 30 10.15 30ZM3.35 18.5C2.6 18.5 2 19.1 2 19.85V26.65C2 27.4 2.6 28 3.35 28H10.15C10.9 28 11.5 27.4 11.5 26.65V18.5H3.35ZM26.65 13.5H17.5C16.95 13.5 16.5 13.05 16.5 12.5V3.35C16.5 1.5 18 0 19.85 0H26.65C28.5 0 30 1.5 30 3.35V10.15C30 12 28.5 13.5 26.65 13.5ZM18.5 11.5H26.65C27.4 11.5 28 10.9 28 10.15V3.35C28 2.6 27.4 2 26.65 2H19.85C19.1 2 18.5 2.6 18.5 3.35V11.5ZM12.5 13.5H3.35C1.5 13.5 0 12 0 10.15V3.35C0 1.5 1.5 0 3.35 0H10.15C12 0 13.5 1.5 13.5 3.35V12.5C13.5 13.05 13.05 13.5 12.5 13.5ZM3.35 2C2.6 2 2 2.6 2 3.35V10.15C2 10.9 2.6 11.5 3.35 11.5H11.5V3.35C11.5 2.6 10.9 2 10.15 2H3.35Z"
                                                        fill="white" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-1 info-title">Total Categories</h6>
                                                <h3 class="font-weight-bold mb-0 fs-20">{{ $total_categories }}</h3>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 col-xxl-2 mb-3 d-flex pr-1">
                                <div class="card shadow-sm h-100 w-100" style="border-radius: 25px;">
                                    <div class=" justify-responsive px-2 pt-4">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="mr-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center svg-bg-size">
                                                <svg width="20" height="20" viewBox="0 0 30 30" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M26.65 30H19.85C18 30 16.5 28.5 16.5 26.65V17.5C16.5 16.95 16.95 16.5 17.5 16.5H26.65C28.5 16.5 30 18 30 19.85V26.65C30 28.5 28.5 30 26.65 30ZM18.5 18.5V26.65C18.5 27.4 19.1 28 19.85 28H26.65C27.4 28 28 27.4 28 26.65V19.85C28 19.1 27.4 18.5 26.65 18.5H18.5ZM10.15 30H3.35C1.5 30 0 28.5 0 26.65V19.85C0 18 1.5 16.5 3.35 16.5H12.5C13.05 16.5 13.5 16.95 13.5 17.5V26.65C13.5 28.5 12 30 10.15 30ZM3.35 18.5C2.6 18.5 2 19.1 2 19.85V26.65C2 27.4 2.6 28 3.35 28H10.15C10.9 28 11.5 27.4 11.5 26.65V18.5H3.35ZM26.65 13.5H17.5C16.95 13.5 16.5 13.05 16.5 12.5V3.35C16.5 1.5 18 0 19.85 0H26.65C28.5 0 30 1.5 30 3.35V10.15C30 12 28.5 13.5 26.65 13.5ZM18.5 11.5H26.65C27.4 11.5 28 10.9 28 10.15V3.35C28 2.6 27.4 2 26.65 2H19.85C19.1 2 18.5 2.6 18.5 3.35V11.5ZM12.5 13.5H3.35C1.5 13.5 0 12 0 10.15V3.35C0 1.5 1.5 0 3.35 0H10.15C12 0 13.5 1.5 13.5 3.35V12.5C13.5 13.05 13.05 13.5 12.5 13.5ZM3.35 2C2.6 2 2 2.6 2 3.35V10.15C2 10.9 2.6 11.5 3.35 11.5H11.5V3.35C11.5 2.6 10.9 2 10.15 2H3.35Z"
                                                        fill="white" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-1 info-title">Total Order Today</h6>
                                                <h3 class="font-weight-bold mb-0 fs-20">{{ $total_order_today ?? 0 }}</h3>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 col-xxl-2 mb-3 d-flex pr-1">
                                <div class="card shadow-sm h-100 w-100" style="border-radius: 25px;">
                                    <div class=" justify-responsive px-2 pt-4">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="mr-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center svg-bg-size">
                                                <svg width="20" height="20" viewBox="0 0 30 30" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M26.65 30H19.85C18 30 16.5 28.5 16.5 26.65V17.5C16.5 16.95 16.95 16.5 17.5 16.5H26.65C28.5 16.5 30 18 30 19.85V26.65C30 28.5 28.5 30 26.65 30ZM18.5 18.5V26.65C18.5 27.4 19.1 28 19.85 28H26.65C27.4 28 28 27.4 28 26.65V19.85C28 19.1 27.4 18.5 26.65 18.5H18.5ZM10.15 30H3.35C1.5 30 0 28.5 0 26.65V19.85C0 18 1.5 16.5 3.35 16.5H12.5C13.05 16.5 13.5 16.95 13.5 17.5V26.65C13.5 28.5 12 30 10.15 30ZM3.35 18.5C2.6 18.5 2 19.1 2 19.85V26.65C2 27.4 2.6 28 3.35 28H10.15C10.9 28 11.5 27.4 11.5 26.65V18.5H3.35ZM26.65 13.5H17.5C16.95 13.5 16.5 13.05 16.5 12.5V3.35C16.5 1.5 18 0 19.85 0H26.65C28.5 0 30 1.5 30 3.35V10.15C30 12 28.5 13.5 26.65 13.5ZM18.5 11.5H26.65C27.4 11.5 28 10.9 28 10.15V3.35C28 2.6 27.4 2 26.65 2H19.85C19.1 2 18.5 2.6 18.5 3.35V11.5ZM12.5 13.5H3.35C1.5 13.5 0 12 0 10.15V3.35C0 1.5 1.5 0 3.35 0H10.15C12 0 13.5 1.5 13.5 3.35V12.5C13.5 13.05 13.05 13.5 12.5 13.5ZM3.35 2C2.6 2 2 2.6 2 3.35V10.15C2 10.9 2.6 11.5 3.35 11.5H11.5V3.35C11.5 2.6 10.9 2 10.15 2H3.35Z"
                                                        fill="white" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-1 info-title">Courier Return</h6>
                                                <h3 class="font-weight-bold mb-0 fs-20">0</h3>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3 col-xxl-2 mb-3 d-flex pl-2">
                                <div class="card shadow-sm h-100 w-100" style="border-radius: 25px;">
                                    <div class=" justify-responsive px-2 pt-4">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="mr-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center svg-bg-size">
                                                <svg width="20" height="20" viewBox="0 0 36 33" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M17.5 29.7704V15.2154C17.9431 15.1277 18.3755 14.9931 18.79 14.8137L29.8817 9.88536C29.9505 10.148 29.9902 10.4174 30 10.6887V14.572C30 14.9035 30.1317 15.2215 30.3661 15.4559C30.6005 15.6903 30.9185 15.822 31.25 15.822C31.5815 15.822 31.8995 15.6903 32.1339 15.4559C32.3683 15.2215 32.5 14.9035 32.5 14.572V10.6887C32.4954 9.64992 32.2307 8.62884 31.73 7.71869V7.69869C31.73 7.68203 31.7083 7.67203 31.7 7.65703C31.046 6.47696 30.0266 5.54115 28.795 4.99036L18.795 0.543692C17.9953 0.185282 17.1288 0 16.2525 0C15.3762 0 14.5097 0.185282 13.71 0.543692L3.71 4.99203C2.4784 5.54282 1.45896 6.47863 0.805 7.65869C0.796667 7.67369 0.781667 7.68369 0.775 7.70036V7.72036C0.273016 8.62963 0.00658798 9.65008 0 10.6887V21.7837C0.000646507 22.9919 0.351398 24.1741 1.00982 25.1872C1.66824 26.2002 2.60613 27.0008 3.71 27.492L13.71 31.937C15.1723 32.6006 16.8358 32.6675 18.3467 32.1237C18.6514 32.0066 18.8982 31.7749 19.0344 31.4782C19.1705 31.1815 19.1852 30.8433 19.0753 30.5359C18.9654 30.2285 18.7395 29.9764 18.4461 29.8333C18.1526 29.6902 17.8149 29.6676 17.505 29.7704H17.5ZM14.73 2.81703C15.2088 2.60193 15.7276 2.49072 16.2525 2.49072C16.7774 2.49072 17.2962 2.60193 17.775 2.81703L27.775 7.26536C28.0473 7.39471 28.3031 7.55644 28.5367 7.74703L23.99 9.76869L11.8983 4.07203L14.73 2.81703ZM8.88 5.41869L20.97 11.1087L17.7833 12.5254C17.3006 12.7344 16.7802 12.8422 16.2542 12.8422C15.7281 12.8422 15.2077 12.7344 14.725 12.5254L3.96333 7.74703C4.19672 7.55659 4.4525 7.3954 4.725 7.26703L8.88 5.41869ZM4.725 25.207C4.06292 24.9125 3.50039 24.4325 3.1055 23.8249C2.71061 23.2173 2.50029 22.5083 2.5 21.7837V10.6887C2.50981 10.4174 2.5495 10.148 2.61833 9.88536L13.7167 14.817C14.1293 14.9946 14.5594 15.1281 15 15.2154V29.7487C14.9083 29.717 14.815 29.6954 14.725 29.6554L4.725 25.207ZM27.9167 16.6537C26.351 16.6537 24.8204 17.118 23.5186 17.9878C22.2167 18.8576 21.202 20.0939 20.6028 21.5404C20.0035 22.9869 19.8467 24.5786 20.152 26.1142C20.4573 27.6499 21.2112 29.0605 22.3181 30.1677C23.4251 31.2749 24.8356 32.0291 26.3712 32.3347C27.9068 32.6404 29.4985 32.4838 30.9451 31.8849C32.3917 31.2859 33.6282 30.2715 34.4983 28.9698C35.3684 27.6682 35.833 26.1377 35.8333 24.572C35.8311 22.4729 34.9964 20.4604 33.5123 18.9759C32.0281 17.4915 30.0158 16.6563 27.9167 16.6537ZM27.9167 29.987C26.8453 29.987 25.798 29.6693 24.9072 29.074C24.0164 28.4788 23.3221 27.6327 22.9122 26.6428C22.5022 25.653 22.3951 24.5638 22.6042 23.513C22.8133 22.4622 23.3294 21.4971 24.0871 20.7396C24.8448 19.9821 25.8101 19.4664 26.861 19.2576C27.9118 19.0488 29.001 19.1563 29.9907 19.5665C30.9804 19.9767 31.8263 20.6712 32.4213 21.5622C33.0163 22.4532 33.3337 23.5006 33.3333 24.572C33.3311 26.0078 32.7597 27.3841 31.7443 28.3992C30.7289 29.4142 29.3524 29.9853 27.9167 29.987ZM30.4667 23.787L29.685 24.5704L30.4667 25.3537C30.5895 25.4681 30.688 25.6061 30.7563 25.7595C30.8246 25.9128 30.8614 26.0783 30.8643 26.2462C30.8673 26.414 30.8364 26.5807 30.7735 26.7364C30.7107 26.892 30.6171 27.0334 30.4984 27.1521C30.3797 27.2708 30.2383 27.3644 30.0827 27.4272C29.927 27.4901 29.7603 27.521 29.5925 27.518C29.4246 27.5151 29.2591 27.4783 29.1058 27.41C28.9524 27.3417 28.8144 27.2432 28.7 27.1204L27.9167 26.337L27.1333 27.1204C27.0189 27.2432 26.8809 27.3417 26.7276 27.41C26.5742 27.4783 26.4087 27.5151 26.2409 27.518C26.073 27.521 25.9063 27.4901 25.7507 27.4272C25.595 27.3644 25.4536 27.2708 25.3349 27.1521C25.2162 27.0334 25.1227 26.892 25.0598 26.7364C24.9969 26.5807 24.9661 26.414 24.969 26.2462C24.972 26.0783 25.0087 25.9128 25.077 25.7595C25.1454 25.6061 25.2439 25.4681 25.3667 25.3537L26.1483 24.5704L25.3667 23.787C25.2439 23.6726 25.1454 23.5346 25.077 23.3813C25.0087 23.2279 24.972 23.0624 24.969 22.8946C24.9661 22.7267 24.9969 22.56 25.0598 22.4044C25.1227 22.2487 25.2162 22.1073 25.3349 21.9886C25.4536 21.8699 25.595 21.7764 25.7507 21.7135C25.9063 21.6506 26.073 21.6197 26.2409 21.6227C26.4087 21.6257 26.5742 21.6624 26.7276 21.7307C26.8809 21.799 27.0189 21.8975 27.1333 22.0204L27.9167 22.802L28.7 22.0204C28.8144 21.8975 28.9524 21.799 29.1058 21.7307C29.2591 21.6624 29.4246 21.6257 29.5925 21.6227C29.7603 21.6197 29.927 21.6506 30.0827 21.7135C30.2383 21.7764 30.3797 21.8699 30.4984 21.9886C30.6171 22.1073 30.7107 22.2487 30.7735 22.4044C30.8364 22.56 30.8673 22.7267 30.8643 22.8946C30.8614 23.0624 30.8246 23.2279 30.7563 23.3813C30.688 23.5346 30.5895 23.6726 30.4667 23.787Z"
                                                        fill="white" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-1 info-title">Out of Stock</h6>
                                                <h3 class="font-weight-bold mb-0 fs-20">{{ $total_out_of_stock }}</h3>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
               {{-- start here All cards section  --}}

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const primaryColor = getComputedStyle(document.documentElement)
                        .getPropertyValue('--primary')
                        .trim();

                    const ctx = document.getElementById('graph-customer').getContext('2d');

                    // Use dynamic data passed from controller
                    const clvData = @json($customer_analytics['clv_data']);

                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: clvData.labels,
                            datasets: [{
                                label: 'Customer Lifetime Value',
                                data: clvData.values,
                                borderColor: primaryColor,
                                backgroundColor: primaryColor + '1A',
                                fill: false,
                                tension: 0.4,
                                borderWidth: 3.5,
                                pointRadius: 5,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: primaryColor,
                                pointBorderWidth: 2.5,
                                pointHoverRadius: 7,
                                pointHoverBorderWidth: 3,
                                pointHoverBackgroundColor: '#fe603b',
                                pointHoverBorderColor: '#fe603b',
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return '৳' + value;
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: '#fff',
                                    titleColor: '#333',
                                    bodyColor: '#333',
                                    borderColor: '#ddd',
                                    borderWidth: 1,
                                    callbacks: {
                                        label: function(context) {
                                            return 'CLV: ৳' + context.parsed.y;
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            </script>

            {{-- SECOND ROW --}}
            <div class="row g-1">
                <div class="col-12 col-md-4 col-lg-6 col-xl-6 col-xxl-4 px-2 mb-4">
                    <div class="dashboard-box-full bg-white p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="fs-16 fw-600">Order Status</h3>
                            <select id="order-filter" class="form-select form-select-sm w-auto px-3 py-1 border-none"
                                style="background-color: #F8F8F9; border-radius:8px;">
                                <option value="day">Day</option>
                                <option value="month" selected>This Month</option>
                                <option value="year">Year</option>
                            </select>
                        </div>
                        <canvas id="order-status-chart" style="max-height: 260px;"></canvas>
                    </div>
                </div>

                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const primaryColor = getComputedStyle(document.documentElement)
                            .getPropertyValue('--primary')
                            .trim();

                        const ctx = document.getElementById('order-status-chart').getContext('2d');
                        let chartInstance = null;
                        let totalOrders = 0;

                        // Custom plugin for center text
                        const centerTextPlugin = {
                            id: 'centerText',
                            afterDraw(chart) {
                                const {
                                    ctx,
                                    chartArea: {
                                        width,
                                        height
                                    }
                                } = chart;
                                ctx.save();
                                ctx.font = 'bold 22px sans-serif';
                                ctx.fillStyle = '#333';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';
                                ctx.fillText(totalOrders, width / 2, height / 2 - 10);
                                ctx.font = '14px sans-serif';
                                ctx.fillStyle = '#666';
                                ctx.fillText('Total orders', width / 2, height / 2 + 15);
                                ctx.restore();
                            }
                        };

                        function loadChart(filter = 'month') {
                            fetch(`{{ route('dashboard.doughnut.get') }}?filter=${filter}`)
                                .then(res => res.json())
                                .then(data => {
                                    totalOrders = data.total;

                                    const chartData = {
                                        labels: data.labels,
                                        datasets: [{
                                            data: data.values,
                                            backgroundColor: [
                                                primaryColor,
                                                'black',
                                                '#fe603b',
                                                '#42c6ce'
                                            ],
                                            borderColor: '#ffffff',
                                            borderWidth: 2,
                                            borderRadius: 15,
                                            spacing: 2,
                                            hoverOffset: 8
                                        }]
                                    };

                                    const options = {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        cutout: '65%',
                                        plugins: {
                                            legend: {
                                                position: 'right',
                                                align: 'center',
                                                labels: {
                                                    usePointStyle: true,
                                                    boxWidth: 10,
                                                    padding: 20,
                                                    color: '#333',
                                                    font: {
                                                        size: 13
                                                    }
                                                }
                                            },
                                            tooltip: {
                                                backgroundColor: '#fff',
                                                titleColor: '#333',
                                                bodyColor: '#333',
                                                borderColor: '#ddd',
                                                borderWidth: 1
                                            }
                                        }
                                    };

                                    if (chartInstance) chartInstance.destroy();
                                    chartInstance = new Chart(ctx, {
                                        type: 'doughnut',
                                        data: chartData,
                                        options: options,
                                        plugins: [centerTextPlugin]
                                    });
                                })
                                .catch(err => console.error('Error loading chart:', err));
                        }

                        // Initial load
                        loadChart();

                        // On dropdown change
                        document.getElementById('order-filter').addEventListener('change', function() {
                            loadChart(this.value);
                        });
                    });
                </script>


                <div class="col-12 col-md-8 col-lg-6 col-xl-6 col-xxl-8 mb-3 px-2">
                    <div class="dashboard-box-full bg-white p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="fs-16 fw-500">Sales Review</h3>
                            <select id="sales-filter" class="form-select form-select-sm w-auto px-3 py-1 border-none"
                                style="background-color: #F8F8F9; border-radius:8px;">
                                <option value="day">Day</option>
                                <option value="month" selected>This Month</option>
                                <option value="year">Year</option>
                            </select>
                        </div>
                        <canvas id="sales-review-chart" style="max-height: 260px;"></canvas>
                    </div>
                </div>

                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
                        const ctx = document.getElementById('sales-review-chart').getContext('2d');
                        let chartInstance = null;

                        function loadSalesChart(filter = 'month') {
                            fetch(`{{ route('dashboard.sales.chart') }}?filter=${filter}`)
                                .then(res => res.json())
                                .then(data => {
                                    const chartData = {
                                        labels: data.labels,
                                        datasets: [
                                            // {
                                            //     label: 'Seller Sales',
                                            //     data: data.seller,
                                            //     borderColor: primaryColor,
                                            //     backgroundColor: primaryColor + '1A',
                                            //     fill: false,
                                            //     tension: 0.4,
                                            //     borderWidth: 3.5,
                                            //     pointRadius: 5,
                                            //     pointBackgroundColor: '#fff',
                                            //     pointBorderColor: primaryColor,
                                            //     pointBorderWidth: 2.5
                                            // },
                                            {
                                                label: 'In-house Sales',
                                                data: data.inhouse,
                                                borderColor: '#ffc188',
                                                backgroundColor: '#ffc1881A',
                                                fill: false,
                                                tension: 0.4,
                                                borderWidth: 3.5,
                                                pointRadius: 5,
                                                pointBackgroundColor: '#fff',
                                                pointBorderColor: '#ffc188',
                                                pointBorderWidth: 2.5
                                            }
                                        ]
                                    };

                                    const options = {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            x: {
                                                grid: {
                                                    display: false
                                                }
                                            },
                                            y: {
                                                beginAtZero: true,
                                                grid: {
                                                    color: 'rgba(0, 0, 0, 0.05)'
                                                },
                                                ticks: {
                                                    callback: function(value) {
                                                        return '৳' + value.toLocaleString();
                                                    }
                                                }
                                            }
                                        },
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                                labels: {
                                                    font: {
                                                        size: 13
                                                    },
                                                    color: '#333'
                                                }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.dataset.label || '';
                                                        let value = context.parsed.y || 0;
                                                        return `${label}: ৳${value.toLocaleString()}`;
                                                    }
                                                },
                                                backgroundColor: '#fff',
                                                titleColor: '#333',
                                                bodyColor: '#333',
                                                borderColor: '#ddd',
                                                borderWidth: 1
                                            }
                                        }
                                    };

                                    if (chartInstance) chartInstance.destroy();
                                    chartInstance = new Chart(ctx, {
                                        type: 'line',
                                        data: chartData,
                                        options
                                    });
                                })
                                .catch(err => console.error('Error loading chart:', err));
                        }

                        // Initial load
                        loadSalesChart();

                        // Dropdown filter event
                        document.getElementById('sales-filter').addEventListener('change', function() {
                            loadSalesChart(this.value);
                        });
                    });
                </script>
            </div>
            {{-- THIRD ROW: Sellers & Inventory Summary --}}

            {{-- Left Column (Total Sellers Card) --}}





            {{-- 4th Left Column (col-4): Top Performing Categories --}}
            <div class="row">
                <div class="col-12 col-md-6 col-lg-6 mb-4 mb-md-0 px-2">
                    <div class="dashboard-card p-4 bg-white rounded-4 shadow-sm h-100">

                        {{-- Card Header --}}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Top Performing Categories</h5>
                            <select id="category-filter" class="form-select form-select-sm w-auto px-3 py-1 border-none "
                                style="background-color: #F8F8F9; border-radius:8px;">
                                <option value="day">Day</option>
                                <option value="month" selected>This Month</option>
                                <option value="year">Year</option>
                            </select>
                        </div>

                        {{-- Table Content --}}
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead>
                                    <tr class="text-small">
                                        <th class="text-secondary">SL.</th>
                                        <th class="text-secondary">Category Name</th>
                                        <th class="text-secondary">Units Sold</th>
                                        <th class="text-secondary text-end">Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody id="top-categories-body">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


                {{-- Right Column (col-8): Top Selling Products --}}
                <div class="col-12 col-md-6 col-lg-6 px-2">
                    <div class="dashboard-card">

                        {{-- Card Header --}}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Top Selling Products</h5>
                            <select id="product-sales-filter"
                                class="form-select form-select-sm w-auto  px-3 py-1 border-none "
                                style="background-color: #F8F8F9; border-radius:8px;">
                                <option value="day">Day</option>
                                <option value="month" selected>This Month</option>
                                <option value="year">Year</option>
                            </select>
                        </div>

                        {{-- Table Content --}}
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead>
                                    <tr class="text-small">
                                        <th class="text-secondary">SL.</th>
                                        <th class="text-secondary">Product Image</th>
                                        <th class="text-secondary">Product Name</th>
                                        <th class="text-secondary">Units Sold</th>
                                        <th class="text-secondary">Average Rating</th>
                                        <th class="text-secondary text-end">Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody id="top-products-body">
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Top Performing Categories --}}
            <script>
                (() => {
                    const tbody = document.getElementById("top-categories-body");
                    const filterSelect = document.getElementById("category-filter");

                    function loadTopCategories(filter = "month") {
                        tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                    </tr>`;

                        fetch(`{{ route('dashboard.top.categories') }}?filter=${filter}`)
                            .then(res => res.json())
                            .then(data => {
                                tbody.innerHTML = "";

                                if (data.length === 0) {
                                    tbody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">No data found</td>
                        </tr>`;
                                    return;
                                }

                                data.forEach((item, index) => {
                                    tbody.innerHTML += `
                        <tr>
                            <td>${index + 1}.</td>
                            <td class="fw-semibold">${item.name}</td>
                            <td>${Number(item.num_of_sale).toLocaleString()}</td>
                            <td class="fw-bold text-end">৳${Number(item.total_sales).toLocaleString()}</td>
                        </tr>`;
                                });
                            })
                            .catch(() => {
                                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-danger py-3">Error loading data</td>
                    </tr>`;
                            });
                    }

                    // Load default
                    loadTopCategories();

                    // Listen to dropdown
                    filterSelect?.addEventListener("change", () => loadTopCategories(filterSelect.value));
                })();
            </script>

            {{-- Top Selling Products --}}
            <script>
                (() => {
                    const filter = document.getElementById("product-sales-filter");
                    const tbody = document.getElementById("top-products-body");

                    function loadProducts(period = "month") {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-secondary">Loading...</td></tr>`;

                        fetch(`{{ route('dashboard.top.products') }}?filter=${period}`)
                            .then(res => res.json())
                            .then(data => {
                                if (!data.length) {
                                    tbody.innerHTML =
                                        `<tr><td colspan="6" class="text-center text-secondary">No data available</td></tr>`;
                                    return;
                                }

                                tbody.innerHTML = data.map((p, index) => `
                    <tr>
                        <td>${index + 1}.</td>
                        <td>
                            <img src="${p.thumbnail_img ? `${p.thumbnail_img}` : '{{ static_asset('assets/img/placeholder.jpg') }}'}"
                                 alt="${p.name}"
                                 width="40" height="40"
                                 class="rounded">
                        </td>
                        <td class="fw-semibold">${p.name}</td>
                        <td>${p.units_sold}</td>
                        <td>${p.rating ? parseFloat(p.rating).toFixed(1) : '—'} <i class="fas fa-star text-warning"></i></td>
                        <td class="fw-bold text-end">৳${Number(p.revenue).toLocaleString()}</td>
                    </tr>
                `).join('');
                            })
                            .catch(() => {
                                tbody.innerHTML =
                                    `<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>`;
                            });
                    }

                    // Initial load
                    loadProducts(filter.value);

                    // Reload on change
                    filter?.addEventListener("change", () => loadProducts(filter.value));
                })();
            </script>





        </div>
    @endcan
@endsection
@section('script')
    <!-- dashboard script -->
    @include('backend.dashboard.dashboard_js')

    <script type="text/javascript">
        AIZ.plugins.chart('#graph-3', {
            type: 'line',
            data: {
                labels: [
                    @foreach ($sales_stat as $month => $row)
                        "{{ $month }}",
                    @endforeach
                ],
                datasets: [{
                        fill: false,
                        borderColor: '#009ef7',
                        label: "{{ translate('Yearly Sales') }}",
                        data: [
                            @foreach ($sales_stat as $row)
                                {{ $row[0]->total }},
                            @endforeach
                        ],

                    },

                ]
            },
            options: {
                legend: {
                    labels: {
                        fontFamily: 'sans-serif',
                        fontColor: "#000",
                        boxWidth: 10,
                        usePointStyle: true
                    },
                    onClick: function() {
                        return '';
                    },
                    position: 'bottom'
                },
                scales: {
                    x: {
                        display: false,
                        drawBorder: false,
                    },
                    y: {
                        display: false,
                        drawBorder: false,
                    },
                }
            }
        });

        AIZ.plugins.chart('#graph-2', {
            type: 'doughnut',
            data: {
                labels: [
                    @foreach ($payment_type_wise_inhouse_sale as $row)
                        "{{ ucwords(str_replace('_', ' ', $row->payment_type)) }}",
                    @endforeach
                ],
                datasets: [{
                    label: 'Total Sales',
                    data: [
                        @foreach ($payment_type_wise_inhouse_sale as $row)
                            {{ $row->total_amount }},
                        @endforeach
                    ],
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'center',
                        labels: {
                            usePointStyle: true,
                            fontSize: 11,
                            boxWidth: 30
                        },
                    },
                }
            }
        })

        function top_category_products(category_id, e) {
            $(".top_category_products").removeClass("active");
            e.classList.add("active");
            $(".top_category_product_table").removeClass("show");
            $("#top_category_product_table_" + category_id).addClass("show");
        }

        function top_sellers_products(seller_id, e) {
            $(".top_sellers_products").removeClass("active");
            e.classList.add("active");
            $(".top_sellers_product_table").removeClass("show");
            $("#top_sellers_product_table_" + seller_id).addClass("show");
        }

        function top_brands_products(brand_id, e) {
            $(".top_brands_products").removeClass("active");
            e.classList.add("active");
            $(".top_brands_product_table").removeClass("show");
            $("#top_brands_product_table_" + brand_id).addClass("show");
        }
    </script>
@endsection
