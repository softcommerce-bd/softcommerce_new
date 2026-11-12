"use client";

/**
 * Shipping region and (optional) pickup.
 *
 * Two separate choices:
 *  1. Shipping Region: Inside City / Outside City. Always shown. It is only the
 *     customer's region; it is sent with the order and never opens a pickup list.
 *  2. Delivery method: Home delivery or Pick up from a store. This belongs to the
 *     admin's Pickup Point feature, so it appears only when the admin has turned
 *     the feature on and added at least one active pickup point. Without that, the
 *     order is simply home delivery and nothing about pickup is drawn.
 *
 * The localStorage keys, the callback signature and the pickup-list endpoint are
 * unchanged; `shippingRegion` is new.
 */

import { useEffect, useState } from "react";
import { Store } from "lucide-react";

import { GroupLegend, IconField, OptionRow } from "@/app/components/checkout/CheckoutFields";

const DeliveryType = ({ onDeliveryTypeChange }) => {
  const [region, setRegion] = useState("inside_city");
  const [deliveryType, setDeliveryType] = useState("home");
  const [pickupPoints, setPickupPoints] = useState([]);
  const [selectedPickupPoint, setSelectedPickupPoint] = useState("");

  // Region, and any earlier delivery choice
  useEffect(() => {
    const savedRegion = localStorage.getItem("shippingRegion");
    setRegion(savedRegion === "outside_city" ? "outside_city" : "inside_city");
    localStorage.setItem("shippingRegion", savedRegion === "outside_city" ? "outside_city" : "inside_city");

    const savedDeliveryType = localStorage.getItem("deliveryType") || "home_delivery";
    const savedPickupPoint = localStorage.getItem("selectedPickupPoint");
    setDeliveryType(savedDeliveryType === "pickup_point" ? "pickup" : "home");
    if (savedPickupPoint) setSelectedPickupPoint(savedPickupPoint);
  }, []);

  // The backend decides whether pickup exists: no active pickup points (or the feature
  // switched off) means an empty list, and the pickup choice is not offered at all.
  useEffect(() => {
    let alive = true;
    fetch(`${process.env.NEXT_PUBLIC_API_URL}/pickup-list`)
      .then((res) => res.json())
      .then((data) => {
        if (!alive) return;
        const list = Array.isArray(data?.data) ? data.data : [];
        setPickupPoints(list);
        if (list.length === 0) {
          // Nothing to pick up from: make sure a stale choice cannot block the order.
          localStorage.setItem("deliveryType", "home_delivery");
          localStorage.removeItem("selectedPickupPoint");
          setDeliveryType("home");
          setSelectedPickupPoint("");
          onDeliveryTypeChange?.("home_delivery", null);
        }
      })
      .catch(() => {
        if (!alive) return;
        localStorage.setItem("deliveryType", "home_delivery");
        localStorage.removeItem("selectedPickupPoint");
        setDeliveryType("home");
      });
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleRegionChange = (value) => {
    setRegion(value);
    localStorage.setItem("shippingRegion", value);
  };

  const handleDeliveryTypeChange = (type) => {
    setDeliveryType(type);
    const deliveryTypeValue = type === "home" ? "home_delivery" : "pickup_point";
    localStorage.setItem("deliveryType", deliveryTypeValue);

    if (type === "home") {
      setSelectedPickupPoint("");
      localStorage.removeItem("selectedPickupPoint");
    }
    onDeliveryTypeChange?.(deliveryTypeValue, type === "home" ? null : selectedPickupPoint);
  };

  const handlePickupPointChange = (e) => {
    const value = e.target.value;
    setSelectedPickupPoint(value);
    if (value) localStorage.setItem("selectedPickupPoint", value);
    else localStorage.removeItem("selectedPickupPoint");
    onDeliveryTypeChange?.("pickup_point", value);
  };

  const pickupAvailable = pickupPoints.length > 0;

  return (
    <>
      <fieldset>
        <GroupLegend>Shipping Region:</GroupLegend>
        <div className="grid grid-cols-2 gap-2.5">
          <OptionRow
            layout="tile"
            name="shippingRegion"
            checked={region === "inside_city"}
            onChange={() => handleRegionChange("inside_city")}
          >
            Inside City
          </OptionRow>
          <OptionRow
            layout="tile"
            name="shippingRegion"
            checked={region === "outside_city"}
            onChange={() => handleRegionChange("outside_city")}
          >
            Outside City
          </OptionRow>
        </div>
      </fieldset>

      {pickupAvailable && (
        <fieldset>
          <GroupLegend>Delivery Method:</GroupLegend>
          <div className="grid grid-cols-2 gap-2.5">
            <OptionRow
              layout="tile"
              name="deliveryType"
              checked={deliveryType === "home"}
              onChange={() => handleDeliveryTypeChange("home")}
            >
              Home Delivery
            </OptionRow>
            <OptionRow
              layout="tile"
              name="deliveryType"
              checked={deliveryType === "pickup"}
              onChange={() => handleDeliveryTypeChange("pickup")}
            >
              Pick up from store
            </OptionRow>
          </div>

          {deliveryType === "pickup" && (
            <div className="animate-slide-down mt-3">
              <IconField
                as="select"
                label="Pickup Location *"
                icon={Store}
                value={selectedPickupPoint}
                onChange={handlePickupPointChange}
                required
              >
                <option value="">Select a pickup point</option>
                {pickupPoints.map((pickup) => (
                  <option key={pickup.id} value={pickup.id}>
                    {pickup.name} — {pickup.address}
                  </option>
                ))}
              </IconField>
            </div>
          )}
        </fieldset>
      )}
    </>
  );
};

export default DeliveryType;
