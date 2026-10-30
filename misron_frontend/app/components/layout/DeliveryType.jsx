"use client";

/**
 * Home delivery vs local pickup choice.
 *
 * Drawn as the reference design's "Shipping Region" control: a legend over a
 * two-up pair of bordered radio tiles, amber when selected. The pickup-point
 * dropdown appears beneath the tiles only when pickup is selected.
 *
 * The reference's tiles are inside-city and outside-city. Misron's shipping
 * cost follows the city picked in the address, so the tiles here carry the
 * choice the store really offers: home delivery or pickup.
 *
 * The localStorage keys, callback signature and pickup-list endpoint are
 * unchanged.
 */

import { useEffect, useState } from "react";
import { Store } from "lucide-react";

import { GroupLegend, IconField, OptionRow } from "@/app/components/checkout/CheckoutFields";

const DeliveryType = ({ onDeliveryTypeChange }) => {
  const [deliveryType, setDeliveryType] = useState("home");
  const [pickupPoints, setPickupPoints] = useState([]);
  const [pickupLoaded, setPickupLoaded] = useState(false);
  const [selectedPickupPoint, setSelectedPickupPoint] = useState("");

  useEffect(() => {
    const savedDeliveryType =
      localStorage.getItem("deliveryType") || "home_delivery";
    const savedPickupPoint = localStorage.getItem("selectedPickupPoint");

    setDeliveryType(savedDeliveryType === "home_delivery" ? "home" : "pickup");

    if (savedPickupPoint) setSelectedPickupPoint(savedPickupPoint);
  }, []);

  // Pickup points are only fetched once pickup is actually chosen.
  useEffect(() => {
    if (deliveryType === "pickup") {
      fetch(`${process.env.NEXT_PUBLIC_API_URL}/pickup-list`)
        .then((res) => res.json())
        .then((data) => setPickupPoints(data.data || []))
        .catch((err) => console.error("Error fetching pickup points:", err))
        .finally(() => setPickupLoaded(true));
    }
  }, [deliveryType]);

  const handleDeliveryTypeChange = (type) => {
    setDeliveryType(type);
    const deliveryTypeValue = type === "home" ? "home_delivery" : "pickup_point";

    localStorage.setItem("deliveryType", deliveryTypeValue);

    if (type === "home") {
      setSelectedPickupPoint("");
      localStorage.removeItem("selectedPickupPoint");
    }

    if (onDeliveryTypeChange) {
      onDeliveryTypeChange(
        deliveryTypeValue,
        type === "home" ? null : selectedPickupPoint,
      );
    }
  };

  const handlePickupPointChange = (e) => {
    const value = e.target.value;
    setSelectedPickupPoint(value);

    if (value) {
      localStorage.setItem("selectedPickupPoint", value);
    } else {
      localStorage.removeItem("selectedPickupPoint");
    }

    if (onDeliveryTypeChange) onDeliveryTypeChange("pickup_point", value);
  };

  return (
    <fieldset>
      <GroupLegend>Shipping Region:</GroupLegend>

      <div className="grid grid-cols-2 gap-2.5">
        <OptionRow
          layout="tile"
          name="deliveryType"
          checked={deliveryType === "home"}
          onChange={() => handleDeliveryTypeChange("home")}
        >
          Inside City (Free Express)
        </OptionRow>

        <OptionRow
          layout="tile"
          name="deliveryType"
          checked={deliveryType === "pickup"}
          onChange={() => handleDeliveryTypeChange("pickup")}
        >
          Outside City (Free Express)
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
            <option value="">
              {pickupLoaded && pickupPoints.length === 0
                ? "No pickup points available"
                : "Select a pickup point"}
            </option>
            {pickupPoints.map((pickup) => (
              <option key={pickup.id} value={pickup.id}>
                {pickup.name} — {pickup.address}
              </option>
            ))}
          </IconField>
        </div>
      )}
    </fieldset>
  );
};

export default DeliveryType;
