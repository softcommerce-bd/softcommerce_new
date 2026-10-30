"use client";

/**
 * Add / edit a saved delivery address.
 *
 * Rebuilt on the shared Modal, Input and Select, so it inherits focus
 * trapping, Escape-to-close and scroll lock. The previous overlay used
 * `bg-opacity-50`, a utility Tailwind v4 removed, so the scrim rendered fully
 * opaque against `bg-main`.
 *
 * Every field label previously carried a decorative icon from three separate
 * icon libraries — a house next to "Full Name", a city skyline next to the
 * area picker. Those are dropped; the labels say what the fields are.
 *
 * The form shape, city source and save callback are unchanged.
 */

import { useState, useEffect } from "react";
import useCities from "@/app/hooks/useCities";

import { Modal, Input, Select, Button } from "../ui";

export default function AddressModal({ isOpen, onClose, onSave, address }) {
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    phone: "",
    address: "",
    city_id: "",
    order_note: "",
  });

  const { cities, loadingCities } = useCities();

  // Editing loads the address; adding resets to a blank form, so a previous
  // edit's values do not carry into the next "Add new".
  useEffect(() => {
    if (address) {
      setFormData(address);
    } else {
      setFormData({
        name: "",
        email: "",
        phone: "",
        address: "",
        city_id: "",
        order_note: "",
      });
    }
  }, [address]);

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave(formData);
  };

  const handleChange = (e) => {
    setFormData((prev) => ({
      ...prev,
      [e.target.name]: e.target.value,
    }));
  };

  return (
    <Modal
      open={isOpen}
      onClose={onClose}
      size="xl"
      title={address ? "Edit address" : "Add address"}
    >
      <form onSubmit={handleSubmit} id="address-form">
        <div className="grid gap-5 md:grid-cols-2">
          <Input
            label="Full name"
            name="name"
            value={formData.name}
            onChange={handleChange}
            placeholder="Your name"
            autoComplete="name"
            required
          />

          <Input
            label="Phone number"
            name="phone"
            value={formData.phone}
            onChange={handleChange}
            placeholder="01XXXXXXXXX"
            autoComplete="tel"
            required
          />

          <Input
            label="Email"
            name="email"
            type="email"
            value={formData.email}
            onChange={handleChange}
            placeholder="you@example.com"
            autoComplete="email"
            required
          />

          <Select
            label="Shipping area"
            name="city_id"
            value={formData.city_id}
            onChange={handleChange}
            required
          >
            <option value="">Select city</option>
            {loadingCities ? (
              <option disabled>Loading…</option>
            ) : (
              cities.map((city) => (
                <option key={city.id} value={city.id}>
                  {city.name} (৳ {city.cost})
                </option>
              ))
            )}
          </Select>

          <div className="md:col-span-2">
            <label htmlFor="address-full" className="field-label">
              Full address
            </label>
            <textarea
              id="address-full"
              name="address"
              value={formData.address}
              onChange={handleChange}
              className="field field-textarea"
              placeholder="House, road, area"
              rows="3"
              autoComplete="street-address"
              required
            />
          </div>

          <div className="md:col-span-2">
            <label htmlFor="address-note" className="field-label">
              Delivery notes{" "}
              <span className="font-normal text-ink-tertiary">(optional)</span>
            </label>
            <textarea
              id="address-note"
              name="order_note"
              value={formData.order_note}
              onChange={handleChange}
              className="field field-textarea min-h-[72px]"
              placeholder="Landmarks or instructions for the courier"
              rows="2"
            />
          </div>
        </div>

        <div className="mt-7 flex justify-end gap-2.5">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit">{address ? "Save changes" : "Add address"}</Button>
        </div>
      </form>
    </Modal>
  );
}
