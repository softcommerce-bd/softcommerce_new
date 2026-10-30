"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import avatarImage from "../../../public/default-avatar.jpg";
import Image from "next/image";
import {
  ShoppingBag,
  Heart,
  ShoppingCart,
  Camera,
  Check,
  AlertCircle,
} from "lucide-react";
import { Button, Input, Skeleton } from "@/app/components/ui";

export default function ProfilePage() {
  const router = useRouter();
  const [profile, setProfile] = useState({
    name: "",
    email: "",
    phone: "",
    avatar: "",
  });
  const [counters, setCounters] = useState({
    cart_item_count: 0,
    wishlist_item_count: 0,
    order_count: 0,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState(null);
  const [passwordData, setPasswordData] = useState({
    password: "",
    password_confirmation: "",
  });

  useEffect(() => {
    fetchProfileData();
    fetchCounters();
  }, []);

  const fetchProfileData = async () => {
    try {
      const token = localStorage.getItem("token");
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/user`, {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });
      const data = await response.json();

      if (data.result) {
        setProfile({
          name: data.data.name || "",
          email: data.data.email || "",
          phone: data.data.phone || "",
          avatar: data.data.avatar || "",
        });
      }
    } catch (error) {
      console.error("Error fetching profile:", error);
    }
  };

  const fetchCounters = async () => {
    try {
      const token = localStorage.getItem("token");
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/profile/counters`,
        {
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        },
      );
      const data = await response.json();

      if (data) {
        setCounters({
          cart_item_count: data.cart_item_count || 0,
          wishlist_item_count: data.wishlist_item_count || 0,
          order_count: data.order_count || 0,
        });
      }
    } catch (error) {
      console.error("Error fetching counters:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      const token = localStorage.getItem("token");
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/profile/update`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            name: profile.name,
            phone: profile.phone,
            password: passwordData.password,
          }),
        },
      );

      const data = await response.json();

      if (data.result) {
        setMessage({
          type: "success",
          text: data.message || "Profile updated successfully!",
        });
        // Clear password fields after successful update
        setPasswordData({ password: "", password_confirmation: "" });
      } else {
        setMessage({
          type: "error",
          text: data.message || "Failed to update profile",
        });
      }
    } catch (error) {
      console.error("Error updating profile:", error);
      setMessage({
        type: "error",
        text: "Failed to update profile. Please try again.",
      });
    } finally {
      setSaving(false);
    }
  };

  const handleImageUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Check file type
    if (!file.type.startsWith("image/")) {
      setMessage({ type: "error", text: "Please upload an image file" });
      return;
    }

    // Check file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      setMessage({ type: "error", text: "Image size should be less than 5MB" });
      return;
    }

    const reader = new FileReader();
    reader.onloadend = async () => {
      try {
        const token = localStorage.getItem("token");
        const base64String = reader.result.split(",")[1];
        const filename = `profile_${Date.now()}.jpg`;

        const response = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/profile/update-image`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              image: base64String,
              filename: filename,
            }),
          },
        );

        const data = await response.json();

        if (data.result) {
          setProfile({ ...profile, avatar: data.path });
          setMessage({
            type: "success",
            text: "Profile picture updated successfully!",
          });
        } else {
          setMessage({
            type: "error",
            text: data.message || "Failed to update profile picture",
          });
        }
      } catch (error) {
        console.error("Error uploading image:", error);
        setMessage({
          type: "error",
          text: "Failed to upload image. Please try again.",
        });
      }
    };
    reader.readAsDataURL(file);
  };

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-8 h-7 w-40" />
        <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-24 w-full" rounded="lg" />
          ))}
        </div>
        <Skeleton className="h-96 w-full" rounded="lg" />
      </div>
    );
  }

  const statCards = [
    { name: "Orders", value: counters.order_count, icon: ShoppingBag },
    { name: "Wishlist", value: counters.wishlist_item_count, icon: Heart },
    { name: "In cart", value: counters.cart_item_count, icon: ShoppingCart },
  ];

  const passwordMismatch =
    passwordData.password &&
    passwordData.password !== passwordData.password_confirmation;

  return (
    <div>
      <header className="mb-8">
        <h1 className="type-title-2 text-ink">Profile</h1>
        <p className="type-callout mt-1.5 text-ink-tertiary">
          Manage your account details.
        </p>
      </header>

      {/* ── Counters ───────────────────────────────────────────────────────*/}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {statCards.map((stat) => (
          <div key={stat.name} className="card p-5">
            <div className="mb-3 flex items-center gap-2 text-ink-tertiary">
              <stat.icon size={15} strokeWidth={1.75} aria-hidden="true" />
              <span className="type-caption font-medium">{stat.name}</span>
            </div>
            <p className="type-title-2 tabular text-ink">{stat.value}</p>
          </div>
        ))}
      </div>

      <form onSubmit={handleSubmit} className="card space-y-8 p-6">
        {message && (
          <div
            role="status"
            className={`flex gap-2.5 rounded-lg p-3.5 ${
              message.type === "success"
                ? "bg-success-tint text-success"
                : "bg-danger-tint text-danger"
            }`}
          >
            {message.type === "success" ? (
              <Check size={17} className="mt-px shrink-0" aria-hidden="true" />
            ) : (
              <AlertCircle size={17} className="mt-px shrink-0" aria-hidden="true" />
            )}
            <p className="type-footnote">{message.text}</p>
          </div>
        )}

        {/* ── Avatar ───────────────────────────────────────────────────────*/}
        <div className="flex items-center gap-5">
          <div className="relative shrink-0">
            <Image
              src={profile.avatar || avatarImage}
              alt=""
              width={80}
              height={80}
              className="h-20 w-20 rounded-full object-cover"
            />

            {/* The label is the visible control; the file input inside it is
                hidden but still does the work. */}
            <label
              htmlFor="avatar-upload"
              className="absolute -bottom-0.5 -right-0.5 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-ink text-ink-inverse shadow-e2 transition-transform duration-fast hover:scale-105 active:scale-95"
            >
              <Camera size={15} strokeWidth={1.75} aria-hidden="true" />
              <span className="sr-only-focusable">Change profile picture</span>
              <input
                id="avatar-upload"
                type="file"
                accept="image/*"
                className="hidden"
                onChange={handleImageUpload}
              />
            </label>
          </div>

          <div>
            <p className="type-subhead font-medium text-ink">Profile picture</p>
            <p className="type-caption mt-1 text-ink-tertiary">
              JPG, PNG or GIF. Up to 5MB.
            </p>
          </div>
        </div>

        <div className="divider" />

        {/* ── Details ──────────────────────────────────────────────────────*/}
        <div className="grid gap-5 md:grid-cols-2">
          <Input
            label="Full name"
            value={profile.name}
            onChange={(e) => setProfile({ ...profile, name: e.target.value })}
            autoComplete="name"
            required
          />

          <Input
            label="Phone number"
            type="tel"
            value={profile.phone}
            onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
            autoComplete="tel"
          />

          <div className="md:col-span-2">
            <Input
              label="Email"
              type="email"
              value={profile.email}
              disabled
              hint="Contact support to change your email address."
            />
          </div>
        </div>

        <div className="divider" />

        {/* ── Password ─────────────────────────────────────────────────────*/}
        <div>
          <h2 className="type-headline mb-1 text-ink">Change password</h2>
          <p className="type-footnote mb-5 text-ink-tertiary">
            Leave these blank to keep your current password.
          </p>

          <div className="grid gap-5 md:grid-cols-2">
            <Input
              label="New password"
              type="password"
              value={passwordData.password}
              onChange={(e) =>
                setPasswordData({ ...passwordData, password: e.target.value })
              }
              autoComplete="new-password"
              placeholder="At least 6 characters"
            />

            <Input
              label="Confirm new password"
              type="password"
              value={passwordData.password_confirmation}
              onChange={(e) =>
                setPasswordData({
                  ...passwordData,
                  password_confirmation: e.target.value,
                })
              }
              autoComplete="new-password"
              placeholder="Re-enter the password"
              error={passwordMismatch ? "Passwords do not match" : undefined}
            />
          </div>
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={passwordMismatch} loading={saving}>
            {saving ? "Saving" : "Save changes"}
          </Button>
        </div>
      </form>
    </div>
  );
}
