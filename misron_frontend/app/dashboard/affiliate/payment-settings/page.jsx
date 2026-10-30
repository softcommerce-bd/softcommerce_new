"use client";

import { useState, useEffect } from "react";
import { getPaymentSettings, storePaymentSettings } from "@/app/services/affiliateService";
import { toast } from "react-hot-toast";
import { Button, Input, Skeleton } from "@/app/components/ui";

export default function PaymentSettings() {
  const [formData, setFormData] = useState({
    paypal_email: "",
    bank_information: "",
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      const response = await getPaymentSettings();
      if (response.result) {
        setFormData({
          paypal_email: response.data.paypal_email || "",
          bank_information: response.data.bank_information || "",
        });
      }
    } catch (error) {
      toast.error("Failed to load payment settings");
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const response = await storePaymentSettings(formData);
      if (response.result) {
        toast.success("Payment settings updated successfully!");
      } else {
        toast.error(response.message || "Failed to update settings");
      }
    } catch (error) {
      toast.error(error.message || "Something went wrong");
    }
  };

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-8 h-7 w-48" />
        <Skeleton className="h-64 w-full" rounded="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="type-title-2 text-ink">Payout settings</h1>
        <p className="type-callout mt-1.5 text-ink-tertiary">
          Where your affiliate withdrawals are sent.
        </p>
      </header>

      <form onSubmit={handleSubmit} className="card space-y-6 p-6">
        <Input
          label="PayPal email"
          type="email"
          placeholder="you@example.com"
          value={formData.paypal_email}
          onChange={(e) =>
            setFormData({ ...formData, paypal_email: e.target.value })
          }
          autoComplete="email"
        />

        <Input
          label="Bank details"
          placeholder="Account number, bank name, branch"
          hint="Used when a payout is sent by bank transfer."
          value={formData.bank_information}
          onChange={(e) =>
            setFormData({ ...formData, bank_information: e.target.value })
          }
        />

        <div className="flex justify-end">
          <Button type="submit">Save settings</Button>
        </div>
      </form>
    </div>
  );
}
