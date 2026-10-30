    <style>
        .dashboard-wide-box {
            border-radius: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            background: #ffffff;
        }

        /* Left section */
        .customer-overview {
            background-color: #ffffff;
            border-top-left-radius: 30px;
            border-bottom-left-radius: 30px;
            z-index: 0;
            position: relative;
        }

        /* Right section: add visible border */
        .customer-graph {
            border: 1px solid rgb(216 211 211 / 20%);
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 1.5rem;
            height: 100%;
            box-sizing: border-box;
            /* ensures border + padding fit perfectly */
        }

        .customer-graph canvas {
            height: 250px !important;
            /* or whatever size you prefer */
            max-height: 300px;
        }


        /* 3rd part css  */
        .dashboard-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #F6F8FA;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .icon-box {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            background: #FEF3E8;
            color: #FF7A00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 12px;
        }

        .symbol-group img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid #fff;
            margin-left: -10px;
            object-fit: cover;
        }

        .symbol-group-seller {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            margin-left: 10px;
        }

        .symbol-group-seller img {
            width: 50px;
            height: 50px;
            /* border-radius: 50%; */
            /* border: 2px solid #fff; */
            margin-left: -10px;
            object-fit: cover;
        }

        .btn-orange {
            background: var(--primary);
            color: #fff;
        }

        .btn-orange:hover {
            background: var(--primary);
            color: #fff;
        }

        .btn-light-orange {
            background: #FFF4E2;
            color: #000;
        }

        /* end of 3rd part css  */

        /* Responsive adjustments */
        @media (max-width: 991px) {
            .customer-graph::before {
                display: none;
            }

            .customer-overview {
                border-radius: 30px 30px 0 0;
            }

            .customer-graph {
                border-radius: 0 0 30px 30px;
            }
        }

        /* =========================
          Base Styles (XS <576px)
        ========================= */
        .executive-label {
            font-size: 14px;
            font-weight: 400;
        }

        .executive-price {
            font-size: 18px;
            font-weight: 500;
        }

        .sale-percent {
            font-size: 12px;
        }

        .customer-svg {
            width: 20px;
            height: 20px;
        }

        .total_customers {
            font-size: 15px;
        }

        .dashboard-stack-box {
            background: #fff;
            border-radius: 16px;
            padding: 18px;
            display: flex;
            flex-direction: column;
        }

        .dashboard-single-box,
        .dashboard-wide-box {
            border-radius: 16px;
            padding: 18px;
        }

        .dashboard-stack {
            min-height: 100%;
        }

        .row>[class*='col-'] {
            display: flex;
        }

        .row>[class*='col-']>div {
            width: 100%;
        }

        .seller-font {
            font-size: 12px;
        }

        .info-title {
            font-size: 15px;
        }

        /* @media (min-width: 1400px) {
                                .custom-xxl-between {
                                    justify-content: space-between !important;
                                }
                            } */

        /* =========================
          SM (min-width: 576px)
        ========================= */
        @media (min-width: 576px) {

            .executive-label {
                font-size: 14px;
                font-weight: 400;
            }

            .executive-price {
                font-size: 18px;
                font-weight: 500;
            }

            .sale-percent {
                font-size: 12px;
            }

            .customer-svg {
                width: 32px;
                height: 25px;
            }

            .total_customers {
                font-size: 21px;
            }

            .customer-price {
                font-size: 15px;
            }

            .top_customers {
                font-size: 12px;
            }

            .symbol-size {
                width: 20px;
                height: 20px;
            }

            .symbol-seller-size {
                width: 20px;
                height: 20px;
            }

            .seller-font {
                font-size: 12px;
            }

            .info-title {

                font-size: 15px;
            }

            .seller-top-margin {
                margin-top: 15px;
            }

            .svg-bg-size {
                width: 50px;
                height: 50px;
            }

            .svg-bg-size svg {
                width: 20px;
                height: 20px;
            }
        }



        /* =========================
           MD (min-width: 768px)
          ========================= */
        @media (min-width: 768px) {

            .executive-label {
                font-size: 14px;
                font-weight: 400;
            }

            .executive-price {
                font-size: 18px;
                font-weight: 500;
            }

            .sale-percent {
                font-size: 12px;
            }

            .customer-svg {
                width: 20px;
                height: 20px;
            }

            .total_customers {
                font-size: 15px;
            }

            .customer-price {
                font-size: 15px;
            }

            .top_customers {
                font-size: 12px;
            }

            .symbol-size {
                width: 20px;
                height: 20px;
            }

            .symbol-seller-size {
                width: 20px;
                height: 20px;
            }

            .seller-font {
                font-size: 12px;
            }

            .info-title {
                font-size: 15px;
            }

            .seller-top-margin {
                margin-top: 15px;
            }

            .svg-bg-size {
                width: 50px;
                height: 50px;
            }

            .svg-bg-size svg {
                width: 20px;
                height: 20px;
            }
        }


        .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #007bff;
        }



        /* =========================
            LG (min-width: 992px)
     ========================= */
        @media (min-width: 992px) {

            .executive-label {
                font-size: 14px;
                font-weight: 400;
            }

            .executive-price {
                font-size: 18px;
                font-weight: 500;
            }

            .sale-percent {
                font-size: 12px;
            }

            .customer-svg {
                width: 20px;
                height: 20px;
            }

            .total_customers {
                font-size: 15px;
            }

            .customer-price {
                font-size: 15px;
            }

            .top_customers {
                font-size: 12px;
            }

            .symbol-size {
                width: 20px;
                height: 20px;
            }

            .symbol-seller-size {
                width: 50px;
                height: 50px;
            }

            .seller-font {
                font-size: 12px;
            }

            .info-title {
                font-size: 15px;
            }

            .seller-top-margin {
                margin-top: 46px;
            }

            .svg-bg-size {
                width: 50px;
                height: 50px;
            }

            .svg-bg-size svg {
                width: 20px;
                height: 20px;
            }
        }



        /* =========================
          XL (min-width: 1200px)
      ========================= */
        @media (min-width: 1200px) {

            .executive-label {
                font-size: 14px;
                font-weight: 400;
            }

            .executive-price {
                font-size: 18px;
                font-weight: 500;
            }

            .sale-percent {
                font-size: 12px;
            }

            .customer-svg {
                width: 20px;
                height: 20px;
            }

            .total_customers {
                font-size: 15px;
            }

            .customer-price {
                font-size: 15px;
            }

            .top_customers {
                font-size: 12px;
            }

            .symbol-size {
                width: 22px;
                height: 24px;
            }

            .symbol-seller-size {
                width: 50px;
                height: 50px;
            }

            .seller-font {
                font-size: 12px;
            }

            .info-title {
                font-size: 15px;
            }

            .seller-top-margin {
                margin-top: 46px;
            }

            .svg-bg-size {
                width: 50px;
                height: 50px;
            }

            .svg-bg-size svg {
                width: 20px;
                height: 20px;
            }


        }


        /* =========================
           XXL (min-width: 1500px)
        ========================= */
        @media (min-width: 1500px) {

            .executive-label {
                font-size: 14px;
                font-weight: 400;
            }

            .executive-price {
                font-size: 18px;
                font-weight: 500;
            }

            .sale-percent {
                font-size: 12px;
            }

            .customer-svg {
                width: 20px;
                height: 20px;
            }

            .total_customers {
                font-size: 20px;
                padding-top: 5px;
            }

            .customer-price {
                font-size: 20px;
            }

            .top_customers {
                font-size: 12px;
            }

            .symbol-size {
                width: 32px;
                height: 32px;
            }

            .symbol-seller-size {
                width: 35px;
                height: 35px;
            }

            .seller-font {
                font-size: 12px;
            }

            .info-title {
                font-size: 15px;
            }

            .justify-responsive {
                display: flex;
                justify-content: space-between;
                align-items: center;

            }

            .seller-top-margin {
                margin-top: 15px;
            }

            .svg-bg-size {
                width: 50px;
                height: 50px;
            }

            .svg-bg-size svg {
                width: 20px;
                height: 20px;
            }
        }



        /* =========================
         MOBILE ONLY (max-width: 767px)
         ========================= */
        @media (max-width: 767px) {

            .executive-label {
                font-size: 14px;
                font-weight: 400;
            }

            .executive-price {
                font-size: 18px;
                font-weight: 500;
            }

            .sale-percent {
                font-size: 12px;
            }

            .customer-svg {
                width: 20px;
                height: 20px;
            }

            .total_customers {
                font-size: 15px;
            }

            .customer-price {
                font-size: 15px;
            }

            .top_customers {
                font-size: 12px;
            }

            .symbol-size {
                width: 20px;
                height: 20px;
            }

            .symbol-seller-size {
                width: 20px;
                height: 20px;
            }

            .seller-font {
                font-size: 12px;
            }

            .info-title {
                font-size: 14px;
            }

            .svg-bg-size {
                width: 40px;
                height: 40px;
            }

            .svg-bg-size svg {
                width: 18px;
                height: 18px;
            }


        }


        .courier-dropdown-content {
            display: none;
        }

        .courier-dropdown-content.active {
            display: block;
        }

        .courier-select-header {
            cursor: pointer;
        }
    </style>