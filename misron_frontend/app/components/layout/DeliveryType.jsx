"use client";

/**
 * Shipping region and (optional) pickup.
 *
 * Two separate choices:
 *  1. Shipping Region: the cities the admin has set up under Shipping Cities (each with its own
 *     cost). A few cities show as tiles, many as a drop-down. The chosen city drives the shipping
 *     cost. Shown only where a city is needed (guest checkout; a signed-in shopper's city comes from
 *     the saved address) and when the admin has at least one active city.
 *  2. Delivery method: Home delivery or Pick up from a store. This belongs to the admin's Pickup
 *     Point feature, so it appears only when that feature is on and has at least one active point.
 *
 * The localStorage keys, the callback signature and the pickup-list endpoint are unchanged.
 */

import { useEffect, useState } from "react";
import { MapPin, Store } from "lucide-react";

import { toTk } from "@/utils/currency";

import { GroupLegend, IconField, OptionRow } from "@/app/components/checkout/CheckoutFields";

const DeliveryType = ({
  onDeliveryTypeChange,
  // Shipping Cities from the admin: [{ id, name, cost }], the chosen id, and its setter.
  cities = [],
  cityId = "",
  onCityChange,
}) => {
  const [deliveryType, setDeliveryType] = useState("home");
  const [pickupPoints, setPickupPoints] = useState([]);
  const [selectedPickupPoint, setSelectedPickupPoint] = useState("");

  // Region, and any earlier delivery choice
  useEffect(() => {
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
      {cities.length > 0 && onCityChange && (
        <fieldset>
          <GroupLegend>Shipping Region:</GroupLegend>
          {cities.length <= 4 ? (
            <div className={`grid gap-2.5 ${cities.length === 1 ? "grid-cols-1" : "grid-cols-2"}`}>
              {cities.map((city) => (
                <OptionRow
                  key={city.id}
                  layout="tile"
                  name="shippingRegion"
                  checked={String(cityId) === String(city.id)}
                  onChange={() => onCityChange(String(city.id))}
                >
                  {city.name}
                  {Number(city.cost) > 0 ? ` (${toTk(city.cost)})` : ""}
                </OptionRow>
              ))}
            </div>
          ) : (
            <IconField
              as="select"
              label="Select your area *"
              icon={MapPin}
              value={String(cityId)}
              onChange={(e) => onCityChange(e.target.value)}
              required
            >
              <option value="">Select region</option>
              {cities.map((city) => (
                <option key={city.id} value={city.id}>
                  {city.name}
                  {Number(city.cost) > 0 ? ` (${toTk(city.cost)})` : ""}
                </option>
              ))}
            </IconField>
          )}
        </fieldset>
      )}

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
