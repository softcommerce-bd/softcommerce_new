"use client";

/**
 * The checkout card: order summary, delivery form, payment and confirm.
 *
 * Restyled to match the reference design's "Fast 1-Click Express Checkout"
 * modal, as a single centred card instead of a form column beside a payment
 * sidebar. It was PaymentSidebar; the name changed because nothing sits
 * beside it any more.
 *
 * The parent (GuestUserCheckout or AuthUserCheckout) owns the delivery fields
 * and passes them as `children`, which render between the order summary and
 * the payment method. Totals, shipping, voucher, gift box, payment methods,
 * the phone OTP step and order placement all live here and are unchanged.
 *
 * The card is a <form>, so empty required fields are caught by the browser
 * before an order is attempted. That is why the confirm button is no longer
 * disabled until the form is valid: a disabled button gave no hint what was
 * missing.
 */

import { ArrowRight, AlertTriangle, ShieldAlert, ShieldCheck, Gift, X } from "lucide-react";
import Link from "next/link";
import { Button, Modal } from "../ui";
import { useAppSelector } from "@/store/hooks";
import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { toast } from "react-hot-toast";
import useCities from "@/app/hooks/useCities";
import { useIPTracking } from "@/app/hooks/useIPTracking";
import { parseAmount, toTk } from "@/utils/currency";
import CheckoutSummary from "@/app/components/checkout/CheckoutSummary";
import {
  GroupLegend,
  OptionBadge,
  OptionRow,
} from "@/app/components/checkout/CheckoutFields";

// Flat charge for gift wrapping, in Taka. Charged once per order, whatever the
// basket holds. Mirrors OrderController::GIFT_BOX_CHARGE — the backend recalculates
// the total it charges, so this figure is for display only.
const GIFT_BOX_CHARGE = 100;

export default function CheckoutCard({
  children,
  selectedAddressId,
  addresses = [],
  guestFormData = null,
  isGuest = false,
  onShippingCostUpdate,
  selectedAddressData = null,
  ipAddress: propIpAddress, // Accept IP from parent if provided
  // Set when the card sits in the checkout popup. It turns the header's close
  // control from a link back to the cart into a button that dismisses the popup.
  onClose,
}) {
  const router = useRouter();
  const { cities } = useCities();
  const { cartData } = useAppSelector((state) => state.cart);
  const { ipAddress: detectedIp, ipLoading } = useIPTracking();
  
  // Use prop IP if provided, otherwise use detected IP
  const ipAddress = propIpAddress || detectedIp;
  
  const [selectedPayment, setSelectedPayment] = useState("cash_on_delivery");
  const [giftBox, setGiftBox] = useState(false);
  const [voucherCode, setVoucherCode] = useState("");
  const [appliedVoucher, setAppliedVoucher] = useState(null);
  const [isProcessing, setIsProcessing] = useState(false);
  // Separate from isProcessing so applying a voucher does not relabel the
  // confirm button "Processing Order...".
  const [isApplyingVoucher, setIsApplyingVoucher] = useState(false);
  const [shippingCost, setShippingCost] = useState(0);
  const [shippingDetails, setShippingDetails] = useState(null);
  const [isCalculatingShipping, setIsCalculatingShipping] = useState(false);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [isCashOnDeliveryEnabled, setIsCashOnDeliveryEnabled] = useState(true);
  const [isLoadingPaymentMethods, setIsLoadingPaymentMethods] = useState(true);
  const [ipRiskLevel, setIpRiskLevel] = useState(null); // 'low', 'medium', 'high'
  const [ipValidated, setIpValidated] = useState(false);

  // OTP State
  const [otpVerified, setOtpVerified] = useState(false);
  const [otpValue, setOtpValue] = useState(["", "", "", "", "", ""]);
  const [otpVerifying, setOtpVerifying] = useState(false);
  const [otpTimer, setOtpTimer] = useState(0);
  const [otpError, setOtpError] = useState('');
  const [showOtpModal, setShowOtpModal] = useState(false);

  // Update shipping cost and notify parent
  const updateShippingCost = (cost) => {
    setShippingCost(cost);
    if (onShippingCostUpdate) {
      onShippingCostUpdate(cost);
    }
  };

  // Fetch payment methods on component mount
  useEffect(() => {
    fetchPaymentMethods();
  }, []);

  // Validate IP when available
  useEffect(() => {
    if (ipAddress && !ipLoading) {
      validateIPForCheckout();
    }
  }, [ipAddress, ipLoading]);

  const validateIPForCheckout = async () => {
    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/ip-check`);
      const result = await response.json();
      
      if (result.blocked) {
        toast.error(result.message || 'Your IP has been blocked. Please contact support.');
        setIpRiskLevel('high');
        return;
      }

      setIpRiskLevel('low');
      setIpValidated(true);
    } catch (error) {
      console.error('IP validation error:', error);
      // Don't block checkout if validation service is down
      setIpValidated(true);
      setIpRiskLevel('unknown');
    }
  };

  // Get phone number from guest form or auth user address
  const getPhoneNumber = () => {
    if (isGuest) {
      return guestFormData?.phone || '';
    }
    const selectedAddress = getSelectedAddress();
    return selectedAddress?.phone || '';
  };

  // OTP timer countdown
  useEffect(() => {
    if (otpTimer <= 0) return;
    const interval = setInterval(() => {
      setOtpTimer((prev) => prev - 1);
    }, 1000);
    return () => clearInterval(interval);
  }, [otpTimer]);

  // Clear any stale digits so a reopened dialog starts empty. The Modal's
  // initialFocus prop puts the caret in the first box.
  useEffect(() => {
    if (!showOtpModal) return;
    setOtpValue(["", "", "", "", "", ""]);
    setOtpError("");
  }, [showOtpModal]);

  const focusOtpInput = (index) => {
    document.getElementById(`modal-otp-input-${index}`)?.focus();
  };

  // Writes a run of digits starting at `index`, so one keystroke fills one box
  // and a pasted or autofilled code spreads across the rest.
  const fillOtpFrom = (index, digits) => {
    const next = [...otpValue];
    for (let i = 0; i < digits.length && index + i < 6; i++) {
      next[index + i] = digits[i];
    }
    setOtpValue(next);
    setOtpError("");
    // Land on the box after the last one written, or stay on the last box once
    // the code is complete.
    focusOtpInput(Math.min(index + digits.length, 5));
  };

  const handleOtpChange = (index, e) => {
    const raw = e.target.value;
    // Strip everything but digits, so letters are rejected and a pasted or
    // SMS-autofilled code becomes a run that fills the remaining boxes.
    let digits = raw.replace(/\D/g, "");

    if (!digits) {
      if (raw === "") {
        const next = [...otpValue];
        next[index] = "";
        setOtpValue(next);
        setOtpError("");
      }
      return;
    }

    // Typing into a box that already holds a digit gives "<old><new>". Keep
    // only the digit the user just typed, or this would consume two boxes.
    const previous = otpValue[index];
    if (digits.length === 2 && previous && digits.startsWith(previous)) {
      digits = digits.slice(1);
    }

    fillOtpFrom(index, digits);
  };

  const handleOtpKeyDown = (index, e) => {
    if (e.key === "Backspace") {
      e.preventDefault();
      const next = [...otpValue];
      if (next[index]) {
        // Clear this box and stay, so a mistyped digit is retyped in place.
        next[index] = "";
        setOtpValue(next);
      } else if (index > 0) {
        next[index - 1] = "";
        setOtpValue(next);
        focusOtpInput(index - 1);
      }
      setOtpError("");
      return;
    }
    if (e.key === "ArrowLeft" && index > 0) {
      e.preventDefault();
      focusOtpInput(index - 1);
    } else if (e.key === "ArrowRight" && index < 5) {
      e.preventDefault();
      focusOtpInput(index + 1);
    }
  };

  const handleOtpPaste = (index, e) => {
    const digits = (e.clipboardData.getData("text") || "").replace(/\D/g, "");
    if (!digits) return;
    e.preventDefault();
    fillOtpFrom(index, digits);
  };

  const fetchPaymentMethods = async () => {
    try {
      setIsLoadingPaymentMethods(true);
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/active-payment-methods`
      );

      if (!response.ok) throw new Error("Failed to fetch payment methods");

      const data = await response.json();

      if (data.success) {
        // Set cash on delivery status
        setIsCashOnDeliveryEnabled(data.cash_on_delivery === "1");
        
        // Process payment methods from API
        const methods = data.data
          .filter(method => method.active === 1)
          .map(method => ({
            value: method.name,
            label: getPaymentMethodLabel(method.name),
            id: method.id
          }));
        
        setPaymentMethods(methods);
        
        // Set default payment method
        if (methods.length > 0 && data.cash_on_delivery !== "1") {
          setSelectedPayment(methods[0].value);
        } else if (data.cash_on_delivery === "1") {
          setSelectedPayment("cash_on_delivery");
        }
      }
    } catch (error) {
      console.error("Error fetching payment methods:", error);
      toast.error("Failed to load payment methods");
    } finally {
      setIsLoadingPaymentMethods(false);
    }
  };

  // Get display label for payment methods
  const getPaymentMethodLabel = (methodName) => {
    const labels = {
      'sslcommerz': 'Pay with SSL Commerz (Card/Mobile Banking)',
      'paystation': 'Pay with card, bKash, Nagad, Rocket or Upay',
      'bkash': 'Pay with bKash',
      'nagad': 'Pay with Nagad',
      'rocket': 'Pay with Rocket',
      'cod': 'Cash on Delivery'
    };
    return labels[methodName] || methodName.charAt(0).toUpperCase() + methodName.slice(1);
  };

  // Extract numeric value from price string (e.g., "Tk 5,000" -> 5000)
  const extractNumber = parseAmount;

  // Calculate subtotal from cart data
  const calculateSubtotal = () => {
    if (!cartData?.data) return 0;

    let subtotal = 0;
    cartData.data.forEach((shop) => {
      subtotal += extractNumber(shop.sub_total);
    });
    return subtotal;
  };

  const subtotal = calculateSubtotal();

  // Find the selected address (for auth users)
  const getSelectedAddress = () => {
    if (!selectedAddressId || !addresses.length) return null;
    return addresses.find((addr) => addr.id === selectedAddressId);
  };

  // Get city name from ID for guest users
  const getCityName = (cityId) => {
    const city = cities?.find((c) => c.id === parseInt(cityId));
    return city?.name || "";
  };

  // Calculate shipping cost when address/city changes
  useEffect(() => {
    if (isGuest) {
      // The city field is not on the form, so waiting for city_id would leave
      // shipping unquoted for good. The cart's rule (flat or free) needs no city.
      if (cartData?.data) {
        calculateGuestShippingCost();
      }
    } else {
      if (selectedAddressId && cartData?.data) {
        calculateShippingCost();
      }
    }
  }, [selectedAddressId, guestFormData?.city_id, cartData, isGuest]);

  // Calculate shipping for auth users
  const calculateShippingCost = async () => {
    if (!cartData?.data || !selectedAddressId) return;

    setIsCalculatingShipping(true);
    try {
      const selectedAddress = getSelectedAddress();

      if (!selectedAddress) {
        console.error("Selected address not found:", selectedAddressId);
        toast.error(
          "Selected address not found. Please select another address.",
        );
        return;
      }

      const sellerList = cartData.data.map((shop) => ({
        seller_id: shop.owner_id,
        shipping_type: "home_delivery",
        shipping_id: null,
      }));

      const userStr = localStorage.getItem("user");
      const userId = userStr ? JSON.parse(userStr).id : null;
      const tempUserId = localStorage.getItem("guest_id");

      const requestBody = {
        user_id: userId,
        temp_user_id: tempUserId,
        seller_list: sellerList,
        address_id: selectedAddressId,
        country_id: selectedAddress.country_id,
        city_id: selectedAddress.city_id,
      };

      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/shipping_cost`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...(localStorage.getItem("token") && {
              Authorization: `Bearer ${localStorage.getItem("token")}`,
            }),
          },
          body: JSON.stringify(requestBody),
        },
      );

      if (!response.ok) throw new Error("Failed to calculate shipping");

      const data = await response.json();

      if (data.result) {
        const cost = extractNumber(data.value_string);
        updateShippingCost(cost);
        setShippingDetails(data);
      }
    } catch (error) {
      console.error("Shipping cost error:", error);
      toast.error("Failed to calculate shipping cost");
    } finally {
      setIsCalculatingShipping(false);
    }
  };

  // Calculate shipping for guest users
  const calculateGuestShippingCost = async () => {
    if (!cartData?.data) return;

    setIsCalculatingShipping(true);
    try {
      const sellerList = cartData.data.map((shop) => ({
        seller_id: shop.owner_id,
        shipping_type: "home_delivery",
        shipping_id: null,
      }));

      const tempUserId = localStorage.getItem("guest_id");

      const requestBody = {
        temp_user_id: tempUserId,
        seller_list: sellerList,
        country_id: 18, // Default country ID for Bangladesh
        city_id: guestFormData?.city_id || null,
      };

      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/shipping_cost`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify(requestBody),
        },
      );

      if (!response.ok) throw new Error("Failed to calculate shipping");

      const data = await response.json();

      if (data.result) {
        const cost = extractNumber(data.value_string);
        updateShippingCost(cost);
        setShippingDetails(data);
      }
    } catch (error) {
      console.error("Shipping cost error:", error);
      toast.error("Failed to calculate shipping cost");
    } finally {
      setIsCalculatingShipping(false);
    }
  };

  const handleApplyVoucher = async () => {
    if (!voucherCode.trim()) {
      toast.error("Please enter a voucher code");
      return;
    }

    setIsApplyingVoucher(true);
    try {
      const token = localStorage.getItem("token");
      const guestId = localStorage.getItem("temp_user_id") || sessionStorage.getItem("temp_user_id");

      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/coupon-apply`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...(token && { Authorization: `Bearer ${token}` }),
          },
          body: JSON.stringify({
            coupon_code: voucherCode,
            ...(!token && guestId && { temp_user_id: guestId }),
          }),
        },
      );

      const data = await response.json();

      if (data.result) {
        setAppliedVoucher({
          ...data,
          code: voucherCode,
          discount: parseFloat(data.coupon_discount ?? 0),
        });
        toast.success("Voucher applied successfully!");
      } else {
        toast.error(data.message || "Invalid voucher code");
      }
    } catch (error) {
      console.error("Voucher error:", error);
      toast.error("Failed to apply voucher");
    } finally {
      setIsApplyingVoucher(false);
    }
  };

  // Asks the backend for a hosted checkout link and sends the browser there.
  // Returns false when the gateway could not be reached, so the caller can
  // fall back to the order page rather than stranding the shopper.
  const startPaystationPayment = async (combinedOrderId) => {
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/paystation/begin`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...(localStorage.getItem("token") && {
              Authorization: `Bearer ${localStorage.getItem("token")}`,
            }),
          },
          body: JSON.stringify({
            payment_type: "cart_payment",
            combined_order_id: combinedOrderId,
          }),
        },
      );

      const data = await response.json();

      if (data.result && data.url) {
        window.location.href = data.url;
        return true;
      }

      toast.error(data.message || "Could not reach the payment gateway");
      return false;
    } catch (error) {
      console.error("Paystation error:", error);
      toast.error("Could not reach the payment gateway");
      return false;
    }
  };

  const placeOrder = async () => {
    setIsProcessing(true);
    try {
      const endpoint = isGuest
        ? `${process.env.NEXT_PUBLIC_API_URL}/guest/order/store`
        : `${process.env.NEXT_PUBLIC_API_URL}/order/store`;

      const deliveryType =
        localStorage.getItem("deliveryType") || "home_delivery";
      const pickupPointId = localStorage.getItem("selectedPickupPoint") || null;

      if (deliveryType === "pickup_point" && !pickupPointId) {
        toast.error("Please select a pickup point");
        setIsProcessing(false);
        return;
      }

      const requestBody = {
        payment_type: selectedPayment,
        delivery_type: deliveryType,
        pickup_point_id: pickupPointId,
        gift_box: giftBox,
        ip_tracking: {
          ip_address: ipAddress,
          user_agent: navigator.userAgent,
          platform: navigator.platform,
          language: navigator.language,
          screen_resolution: `${window.screen.width}x${window.screen.height}`,
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
          color_depth: window.screen.colorDepth,
          cookies_enabled: navigator.cookieEnabled,
          timestamp: new Date().toISOString(),
          page_url: window.location.href,
          referrer: document.referrer,
          risk_level: ipRiskLevel,
        },
      };

      if (isGuest) {
        requestBody.temp_user_id = localStorage.getItem("guest_id");
        requestBody.guest_info = {
          name: guestFormData.name,
          email: guestFormData.email || "",
          phone: guestFormData.phone,
          city_id: guestFormData.city_id,
          city_name: getCityName(guestFormData.city_id),
          address: guestFormData.address,
          order_note: guestFormData.order_note || "",
        };
      } else {
        const selectedAddress = getSelectedAddress();
        if (selectedAddress) {
          requestBody.shipping_address = {
            name: selectedAddress.name || "",
            email: selectedAddress.email || "",
            phone: selectedAddress.phone || "",
            address: selectedAddress.address || "",
            order_note: selectedAddress.order_note || "",
            city: selectedAddress.city_name || "",
            country: "Bangladesh",
            postal_code: selectedAddress.postal_code || "",
          };
        }
      }

      const headers = {
        "Content-Type": "application/json",
        Accept: "application/json",
      };

      if (!isGuest) {
        const token = localStorage.getItem("token");
        if (token) {
          headers.Authorization = `Bearer ${token}`;
        }
      }

      const response = await fetch(endpoint, {
        method: "POST",
        headers,
        body: JSON.stringify(requestBody),
      });

      const data = await response.json();

      if (data.result) {
        // Gateways need the order to exist before they can be handed an
        // amount and an invoice, so the shopper leaves for the checkout page
        // only once the order is stored — unpaid until the gateway says
        // otherwise.
        if (selectedPayment === "paystation" && data.combined_order_id) {
          const redirected = await startPaystationPayment(data.combined_order_id);
          if (redirected) return;
          // Falls through to the order page when the gateway is unreachable,
          // so the order the shopper just placed is not lost.
        }

        toast.success("Order placed successfully!");
        if (data.combined_order_id) {
          const detailsUrl = isGuest
            ? `/confirm-order/${data.combined_order_id}?guest=true`
            : `/confirm-order/${data.combined_order_id}`;
          router.push(detailsUrl);
        } else {
          router.push("/orders");
        }
      } else {
        toast.error(data.message || "Failed to place order");
        if (data.blocked_by_fraud) {
          setIpRiskLevel('high');
          toast.error("Your order has been flagged for security review. Please contact support.");
        }
      }
    } catch (error) {
      console.error("Order error:", error);
      toast.error("Failed to place order");
    } finally {
      setIsProcessing(false);
    }
  };

  const handleConfirmOrder = async () => {
    if (ipRiskLevel === 'high') {
      toast.error("Order cannot be processed. Please contact support.");
      return;
    }

    if (!cartData?.data || cartData.data.length === 0) {
      toast.error("Your cart is empty");
      return;
    }

    if (isGuest) {
      if (
        !guestFormData?.name ||
        !guestFormData?.phone ||
        // !guestFormData?.city_id ||
        !guestFormData?.address
      ) {
        toast.error("Please fill in all required fields");
        return;
      }
    } else {
      const token = localStorage.getItem("token");
      if (!token) {
        toast.error("Please login to continue");
        router.push("/login");
        return;
      }
      if (!selectedAddressId) {
        toast.error("Please select a delivery address");
        return;
      }
    }

    if (!otpVerified) {
      setIsProcessing(true);
      const phone = getPhoneNumber();
      if (!phone) {
        toast.error("Phone number is required");
        setIsProcessing(false);
        return;
      }
      try {
        const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/checkout/send-otp`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(localStorage.getItem('token') && {
              Authorization: `Bearer ${localStorage.getItem('token')}`,
            }),
          },
          body: JSON.stringify({
            phone,
            temp_user_id: localStorage.getItem('guest_id'),
          }),
        });
        const data = await response.json();
        if (data.result) {
          setOtpTimer(120);
          setShowOtpModal(true);
        } else if (data.message && data.message.includes('not enabled')) {
          setOtpVerified(true);
          placeOrder();
        } else {
          toast.error(data.message || 'Failed to send OTP');
        }
      } catch (error) {
        console.error('Send OTP error:', error);
        toast.error('Failed to send OTP');
      } finally {
        setIsProcessing(false);
      }
    } else {
      placeOrder();
    }
  };

  const handleModalVerifyOtp = async () => {
    const otpString = otpValue.join('');
    if (otpString.length !== 6) {
      setOtpError('Please enter complete 6-digit OTP');
      return;
    }
    const phone = getPhoneNumber();
    if (!phone) return;

    setOtpVerifying(true);
    setOtpError('');
    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/checkout/verify-otp`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(localStorage.getItem('token') && {
            Authorization: `Bearer ${localStorage.getItem('token')}`,
          }),
        },
        body: JSON.stringify({
          phone,
          otp: otpString,
          temp_user_id: localStorage.getItem('guest_id'),
        }),
      });
      const data = await response.json();
      if (data.result) {
        setOtpVerified(true);
        setShowOtpModal(false);
        toast.success('OTP verified successfully');
        placeOrder();
      } else {
        setOtpError(data.message || 'Invalid OTP');
        toast.error(data.message || 'Invalid OTP');
        // Clear the boxes so the retry starts from an empty field.
        setOtpValue(["", "", "", "", "", ""]);
        setTimeout(() => focusOtpInput(0), 0);
      }
    } catch (error) {
      console.error('Verify OTP error:', error);
      setOtpError('Failed to verify OTP');
      toast.error('Failed to verify OTP');
    } finally {
      setOtpVerifying(false);
    }
  };

  // Verify as soon as the sixth digit lands, so typing or pasting the code is
  // the whole interaction. The Verify button stays for retries.
  useEffect(() => {
    if (!showOtpModal || otpVerifying || otpVerified) return;
    if (otpValue.join("").length !== 6) return;
    handleModalVerifyOtp();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [otpValue, showOtpModal]);

  const giftBoxCharge = giftBox ? GIFT_BOX_CHARGE : 0;

  // The gift box is added after the voucher discount, since the voucher was
  // checked against subtotal + shipping and wrapping should not enlarge it.
  const finalTotal =
    subtotal + shippingCost - (appliedVoucher?.discount || 0) + giftBoxCharge;

  const paymentOptions = [
    ...(isCashOnDeliveryEnabled
      ? [
          {
            value: "cash_on_delivery",
            label: "Cash on Delivery (Pay when you receive your order)",
            badge: <OptionBadge tone="filled">Popular</OptionBadge>,
          },
        ]
      : []),
    ...paymentMethods.map((method) => ({
      value: method.value,
      label: method.label,
      badge: <OptionBadge>Online</OptionBadge>,
    })),
  ];

  // The form-validity checks are gone from here: the browser flags empty
  // required fields on submit, and handleConfirmOrder explains a missing
  // address, so the button no longer sits disabled without saying why.
  const isConfirmDisabled =
    isProcessing ||
    !cartData?.data ||
    cartData.data.length === 0 ||
    isCalculatingShipping ||
    isLoadingPaymentMethods ||
    paymentOptions.length === 0 ||
    ipRiskLevel === "high";

  // "quoted" once the shipping endpoint has answered, so a zero cost reads as
  // free shipping rather than as a figure not yet worked out.
  const shippingStatus = isCalculatingShipping
    ? "calculating"
    : shippingDetails
      ? "quoted"
      : "pending";

  const handleSubmit = (e) => {
    e.preventDefault();
    handleConfirmOrder();
  };

  return (
    <>
      {/* In the popup the dialog supplies the frame and does the scrolling, so
          the card drops its own border, shadow and overflow clip — the clip
          would stop the header sticking to the top of the scroll area. */}
      <div
        className={
          onClose
            ? "w-full bg-[#FAF8F5]"
            : "mx-auto w-full max-w-xl overflow-hidden rounded-2xl border border-stone-200/80 bg-[#FAF8F5] shadow-2xl"
        }
      >
        {/* ── Header ──────────────────────────────────────────────────────
            On the page the close control leads back to the cart, since there
            is no popup to dismiss. */}
        <div
          className={`flex items-center justify-between border-b border-stone-200 bg-white p-4 sm:p-5 ${
            onClose ? "sticky top-0 z-10" : ""
          }`}
        >
          <div className="flex items-center gap-2">
            <span className="h-2.5 w-2.5 rounded-full bg-amber-600" aria-hidden="true" />
            <h1 className="type-modal-serif text-lg text-stone-900 sm:text-xl">
              Checkout
            </h1>
          </div>

          {onClose ? (
            <button
              type="button"
              onClick={onClose}
              aria-label="Close checkout"
              className="rounded-full p-1.5 text-stone-400 transition-colors hover:bg-stone-100 hover:text-stone-800"
            >
              <X className="h-5 w-5" aria-hidden="true" />
            </button>
          ) : (
            <Link
              href="/view-cart"
              aria-label="Back to cart"
              className="rounded-full p-1.5 text-stone-400 transition-colors hover:bg-stone-100 hover:text-stone-800"
            >
              <X className="h-5 w-5" aria-hidden="true" />
            </Link>
          )}
        </div>

        <div className="p-4 sm:p-6">
          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Delivery fields from the parent, then gift box and payment,
                share one stack so the spacing is the reference's `space-y-3`. */}
            <div className="space-y-3 text-xs">
              {children}

              {/* One flat charge for the whole order, however many products it
                  holds. A checkbox, since it is a single on/off add-on. */}
              {/* <fieldset>
                <GroupLegend>Gift Option:</GroupLegend>
                <OptionRow
                  type="checkbox"
                  name="gift_box"
                  checked={giftBox}
                  onChange={(e) => setGiftBox(e.target.checked)}
                  badge={
                    <span className="shrink-0 text-[11px] font-medium tabular-nums text-stone-600">
                      +{toTk(GIFT_BOX_CHARGE)}
                    </span>
                  }
                >
                  <span className="flex items-center gap-1.5">
                    <Gift className="h-3.5 w-3.5" aria-hidden="true" />
                    Add a gift box
                  </span>
                  <span className="block pl-5 text-[11px] font-normal text-stone-400">
                    We wrap your whole order as a gift.
                  </span>
                </OptionRow>
              </fieldset> */}

              {/* Rendered from one list so cash on delivery and the API's
                  methods share a single treatment. */}
              <fieldset>
                <GroupLegend>Payment Method:</GroupLegend>

                {isLoadingPaymentMethods ? (
                  <div className="space-y-2" aria-hidden="true">
                    <div className="h-11 animate-pulse rounded-lg bg-stone-100" />
                    <div className="h-11 animate-pulse rounded-lg bg-stone-100" />
                  </div>
                ) : paymentOptions.length > 0 ? (
                  <div className="space-y-2">
                    {paymentOptions.map((option) => (
                      <OptionRow
                        key={option.value}
                        name="payment"
                        value={option.value}
                        checked={selectedPayment === option.value}
                        onChange={(e) => setSelectedPayment(e.target.value)}
                        badge={option.badge}
                      >
                        {option.label}
                      </OptionRow>
                    ))}
                  </div>
                ) : (
                  <p role="alert" className="text-rose-700">
                    No payment methods available. Please contact support.
                  </p>
                )}
              </fieldset>
            </div>

            {/* ── Risk notices ───────────────────────────────────────────*/}
            {ipRiskLevel === "medium" && (
              <div className="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                <AlertTriangle className="mt-px h-4 w-4 shrink-0" aria-hidden="true" />
                <p>Additional verification may be required for this order.</p>
              </div>
            )}

            {ipRiskLevel === "high" && (
              <div
                role="alert"
                className="flex gap-2 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800"
              >
                <ShieldAlert className="mt-px h-4 w-4 shrink-0" aria-hidden="true" />
                <p>This order cannot be processed. Please contact support.</p>
              </div>
            )}

            <button
              type="submit"
              disabled={isConfirmDisabled}
              className="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#A27339] py-3.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-[#8B5F2A] disabled:cursor-not-allowed disabled:opacity-75"
            >
              {isProcessing ? (
                <span>Processing Order...</span>
              ) : (
                <>
                  <span>Confirm Order — {toTk(finalTotal)}</span>
                  <ArrowRight className="h-4 w-4" aria-hidden="true" />
                </>
              )}
            </button>

            {/* The order summary sits below the confirm button, so the form
                and payment choice come first, then the totals, then the
                security note and terms close the page. */}
            <CheckoutSummary
              onNavigate={onClose}
              subtotal={subtotal}
              shippingCost={shippingCost}
              shippingStatus={shippingStatus}
              discount={appliedVoucher?.discount || 0}
              voucherCode={appliedVoucher?.code}
              giftBoxCharge={giftBoxCharge}
              total={finalTotal}
              voucher={{
                code: voucherCode,
                setCode: setVoucherCode,
                applied: !!appliedVoucher,
                applying: isApplyingVoucher,
                apply: handleApplyVoucher,
                remove: () => setAppliedVoucher(null),
              }}
            />

            <div className="flex items-center justify-center gap-2 pt-1 text-[11px] text-stone-500">
              <ShieldCheck className="h-4 w-4 text-emerald-600" aria-hidden="true" />
              <span>Your information is 100% secure</span>
            </div>

            <p className="text-center text-[11px] text-stone-400">
              By confirming, you agree to our{" "}
              <a href="/terms" className="underline underline-offset-2 hover:text-stone-600">
                Terms of Service
              </a>{" "}
              and{" "}
              <a href="/privacy" className="underline underline-offset-2 hover:text-stone-600">
                Privacy Policy
              </a>
              .
            </p>
          </form>
        </div>
      </div>

      {/* ── OTP verification ───────────────────────────────────────────────
          Uses the shared Modal, so it now traps focus and closes on Escape.
          The dialog cannot be dismissed by clicking the scrim, since doing so
          mid-verification would strand the order. */}
      <Modal
        open={showOtpModal}
        onClose={() => setShowOtpModal(false)}
        size="sm"
        title="Verify your phone"
        description={`Enter the 6-digit code sent to ${getPhoneNumber()}`}
        closeOnBackdrop={false}
        initialFocus="#modal-otp-input-0"
      >
        {/* py-1 keeps the 3px focus ring clear of the body's overflow clip. */}
        <div className="mb-4 flex justify-center gap-2 py-1">
          {otpValue.map((digit, index) => (
            <input
              key={index}
              id={`modal-otp-input-${index}`}
              type="text"
              inputMode="numeric"
              pattern="[0-9]*"
              autoComplete={index === 0 ? "one-time-code" : "off"}
              // No maxLength: the browser's SMS autofill writes the whole code
              // into one box, and handleOtpChange spreads it across the rest.
              value={digit}
              onChange={(e) => handleOtpChange(index, e)}
              onKeyDown={(e) => handleOtpKeyDown(index, e)}
              onPaste={(e) => handleOtpPaste(index, e)}
              aria-label={`Digit ${index + 1} of 6`}
              className="tabular h-12 w-11 rounded-md border border-hairline bg-surface text-center text-lg font-semibold text-ink transition-all duration-fast focus:border-brand focus:shadow-[0_0_0_3px_var(--brand-tint)] focus:outline-none"
            />
          ))}
        </div>

        {otpError && (
          <p role="alert" className="type-footnote mb-4 text-center text-danger">
            {otpError}
          </p>
        )}

        <Button
          size="lg"
          fullWidth
          onClick={handleModalVerifyOtp}
          disabled={otpValue.join("").length !== 6}
          loading={otpVerifying}
        >
          {otpVerifying ? "Verifying" : "Verify"}
        </Button>

        <div className="mt-4 text-center">
          {otpTimer > 0 ? (
            <span className="type-caption tabular text-ink-tertiary">
              Resend in {Math.floor(otpTimer / 60)}:
              {(otpTimer % 60).toString().padStart(2, "0")}
            </span>
          ) : (
            <button
              type="button"
              onClick={() => {
                setOtpValue(["", "", "", "", "", ""]);
                setOtpTimer(120);
                const phone = getPhoneNumber();
                if (phone) {
                  fetch(`${process.env.NEXT_PUBLIC_API_URL}/checkout/send-otp`, {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/json",
                      Accept: "application/json",
                      ...(localStorage.getItem("token") && {
                        Authorization: `Bearer ${localStorage.getItem("token")}`,
                      }),
                    },
                    body: JSON.stringify({
                      phone,
                      temp_user_id: localStorage.getItem("guest_id"),
                    }),
                  });
                }
              }}
              className="type-caption font-semibold text-ink hover:underline"
            >
              Resend code
            </button>
          )}
        </div>
      </Modal>
    </>
  );
}
