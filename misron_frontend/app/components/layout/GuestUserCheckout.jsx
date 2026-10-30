// app/components/layout/GuestUserCheckout.jsx
'use client';

import useCities from "@/app/hooks/useCities";
import { User, Phone, Mail, Building2, MapPin, StickyNote } from "lucide-react";
import DeliveryType from "./DeliveryType";
import CheckoutCard from "./CheckoutCard";
import { useEffect, useState, useCallback } from "react";
import { useCheckoutGTM } from "@/components/GTM/useCheckoutGTM";
import { useCart } from "@/store/hooks/useCart";
import { useDraft } from "@/app/hooks/useDraft";
import { useIPTracking } from "@/app/hooks/useIPTracking";
import IPBlockWarning from "@/app/components/checkout/IPBlockWarning";
import { IconField, SectionHeading } from "@/app/components/checkout/CheckoutFields";

export default function GuestUserCheckout({ onClose }) {
  const { cities, loadingCities } = useCities();
  const [shippingCost, setShippingCost] = useState(0);
  const { cartData } = useCart();
  const { saveDraft, loadDraft, draftData } = useDraft(cartData);
  const { ipAddress, ipLoading } = useIPTracking();
  const [ipBlocked, setIpBlocked] = useState(false);
  const [ipBlockReason, setIpBlockReason] = useState('');
  
  useCheckoutGTM();
  
  const [deliveryInfo, setDeliveryInfo] = useState({
    deliveryType: 'home_delivery',
    pickupPointId: null
  });

  const [formData, setFormData] = useState({
    name: "",
    email: "",
    phone: "",
    city_id: "",
    address: "",
    order_note: ""
  });

  // Define IP validation function BEFORE using it in useEffect
  const validateIPAddress = useCallback(async () => {
    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/ip-check`);
      const result = await response.json();
      
      if (result.blocked) {
        setIpBlocked(true);
        setIpBlockReason(result.message || 'Your IP has been blocked due to suspicious activity.');
      }
    } catch (error) {
      console.error('IP validation error:', error);
      // Don't block if validation service is down
    }
  }, [ipAddress]);

  // Now validateIPAddress is defined, so we can call it
  useEffect(() => {
    if (ipAddress && !ipLoading) {
      validateIPAddress();
    }
  }, [ipAddress, ipLoading, validateIPAddress]);

  // Load draft data on mount
  useEffect(() => {
    const loadSavedDraft = async () => {
      const savedDraft = await loadDraft();
      if (savedDraft) {
        setFormData({
          name: savedDraft.name || "",
          email: savedDraft.email || "",
          phone: savedDraft.phone || "",
          city_id: savedDraft.city_id || "",
          address: savedDraft.address || "",
          order_note: savedDraft.order_note || ""
        });
        
        if (savedDraft.delivery_type) {
          setDeliveryInfo({
            deliveryType: savedDraft.delivery_type,
            pickupPointId: savedDraft.pickup_point_id
          });
        }
      }
    };
    
    loadSavedDraft();
  }, []);

  // Auto-save draft when form data changes
  useEffect(() => {
    const timer = setTimeout(() => {
      if (formData.name || formData.phone || formData.address) {
        saveDraft({
          ...formData,
          delivery_type: deliveryInfo.deliveryType,
          pickup_point_id: deliveryInfo.pickupPointId
        });
      }
    }, 1000);
    
    return () => clearTimeout(timer);
  }, [formData, deliveryInfo, saveDraft]);

  const handleDeliveryTypeChange = (deliveryType, pickupPointId) => {
    setDeliveryInfo({
      deliveryType,
      pickupPointId
    });
    
    localStorage.setItem('deliveryType', deliveryType);
    if (pickupPointId) {
      localStorage.setItem('selectedPickupPoint', pickupPointId);
    } else {
      localStorage.removeItem('selectedPickupPoint');
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleShippingCostUpdate = (cost) => {
    setShippingCost(cost);
  };

  const handleRetry = () => {
    setIpBlocked(false);
    setIpBlockReason('');
    validateIPAddress();
  };

  if (ipBlocked) {
    return (
      <div className="py-10">
        <IPBlockWarning reason={ipBlockReason} onRetry={handleRetry} />
      </div>
    );
  }

  // The fields render inside CheckoutCard, between its order summary and its
  // payment method, as one stack — hence the fragment, not a wrapper.
  return (
    <CheckoutCard
      guestFormData={{
        ...formData,
        ip_address: ipAddress,
      }}
      isGuest={true}
      onShippingCostUpdate={handleShippingCostUpdate}
      ipAddress={ipAddress}
      onClose={onClose}
    >
      <SectionHeading>Shipping &amp; Contact Details</SectionHeading>

      <IconField
        label="Your Full Name *"
        icon={User}
        name="name"
        value={formData.name}
        onChange={handleChange}
        placeholder="e.g. Farhana Karim"
        autoComplete="name"
        required
      />

      <IconField
        label="Mobile Phone Number *"
        icon={Phone}
        type="tel"
        inputMode="tel"
        name="phone"
        value={formData.phone}
        onChange={handleChange}
        placeholder="e.g. 017XXXXXXXX"
        autoComplete="tel"
        className="tabular-nums"
        required
      />

      {/* <IconField
        label="Email Address (optional)"
        icon={Mail}
        type="email"
        name="email"
        value={formData.email}
        onChange={handleChange}
        placeholder="For your order confirmation"
        autoComplete="email"
      /> */}

      {/* <IconField
        as="select"
        label="City *"
        icon={Building2}
        name="city_id"
        value={formData.city_id}
        onChange={handleChange}
        required
      >
        <option value="">Select city</option>
        {loadingCities ? (
          <option disabled>Loading cities…</option>
        ) : (
          cities.map((city) => (
            <option key={city.id} value={city.id}>
              {city.name} (৳ {city.cost})
            </option>
          ))
        )}
      </IconField> */}

      <IconField
        as="textarea"
        label="Detailed Delivery Address *"
        icon={MapPin}
        name="address"
        value={formData.address}
        onChange={handleChange}
        placeholder="House / Apt, Road, Area, City"
        rows={2}
        autoComplete="street-address"
        required
      />

      {/* <IconField
        as="textarea"
        label="Order Note (optional)"
        icon={StickyNote}
        name="order_note"
        value={formData.order_note}
        onChange={handleChange}
        placeholder="Anything we should know about this delivery"
        rows={2}
      /> */}

      <DeliveryType onDeliveryTypeChange={handleDeliveryTypeChange} />
    </CheckoutCard>
  );
}
