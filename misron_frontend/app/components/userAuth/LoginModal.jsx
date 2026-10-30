"use client";

/**
 * Sign-in dialog.
 *
 * Rebuilt on the shared Modal, Input and Button. The dialog now traps focus,
 * closes on Escape and locks page scroll, none of which the previous
 * hand-rolled overlay did. The lilac gradient panel and red circular close
 * button are gone; this is a plain raised surface.
 *
 * The auth request, payload shape and success handling are unchanged.
 */

import Link from "next/link";
import { useState } from "react";
import toast from "react-hot-toast";
import { Mail, Phone, Lock, AlertCircle } from "lucide-react";

import { Modal, Input, Button } from "../ui";

export default function LoginModal({ open, onClose, onSwitch, onLoginSuccess }) {
  const [usePhone, setUsePhone] = useState(false);
  const [emailOrPhone, setEmailOrPhone] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState([]);

  const handleLogin = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrors([]);

    if (!emailOrPhone || !password) {
      setErrors(["Please fill in all fields"]);
      setLoading(false);
      return;
    }

    const payload = {
      email: emailOrPhone, // The backend expects 'email' field
      password: password,
      login_by: usePhone ? "phone" : "email",
      user_type: "customer",
    };

    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/auth/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "include",
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (data.result === true) {
        if (data.access_token) {
          localStorage.setItem("token", data.access_token);
          localStorage.setItem("user", JSON.stringify(data.user));
        }

        toast.success(`Welcome back ${data.user?.name || ""}!`);

        if (onLoginSuccess) onLoginSuccess(data.user);

        onClose();
      } else {
        if (Array.isArray(data.message)) {
          setErrors(data.message);
          toast.error(data.message[0]);
        } else if (typeof data.message === "string") {
          setErrors([data.message]);
          toast.error(data.message);
        } else {
          toast.error("Login failed. Please check your credentials.");
        }
      }
    } catch (err) {
      console.error("Login error:", err);
      toast.error("Network error, please try again.");
      setErrors(["Connection error. Please check your internet and try again."]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="sm"
      title="Welcome back"
      description="Sign in for faster checkout and order tracking."
    >
      {errors.length > 0 && (
        <div
          role="alert"
          className="mb-5 flex gap-2.5 rounded-lg bg-danger-tint p-3.5 text-danger"
        >
          <AlertCircle size={17} className="mt-px shrink-0" aria-hidden="true" />
          <div className="type-footnote space-y-0.5">
            {errors.map((e, i) => (
              <p key={i}>{e}</p>
            ))}
          </div>
        </div>
      )}

      <form onSubmit={handleLogin} className="space-y-4">
        {/* The identifier field swaps between email and phone in place, so the
            form does not change height when the method is toggled. */}
        <Input
          key={usePhone ? "phone" : "email"}
          label={usePhone ? "Phone number" : "Email"}
          type={usePhone ? "tel" : "email"}
          icon={usePhone ? Phone : Mail}
          placeholder={usePhone ? "01XXXXXXXXX" : "you@example.com"}
          value={emailOrPhone}
          onChange={(e) => setEmailOrPhone(e.target.value)}
          autoComplete={usePhone ? "tel" : "email"}
          required
          disabled={loading}
        />

        <div>
          <Input
            label="Password"
            type="password"
            icon={Lock}
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            minLength={6}
            required
            disabled={loading}
          />
          <div className="mt-2 flex justify-end">
            <Link
              href="/forgot-password"
              onClick={onClose}
              className="type-footnote font-medium text-ink-secondary hover:text-ink"
            >
              Forgot password?
            </Link>
          </div>
        </div>

        <Button type="submit" size="lg" fullWidth loading={loading}>
          {loading ? "Signing in" : "Sign in"}
        </Button>

        <button
          type="button"
          onClick={() => {
            setUsePhone(!usePhone);
            setEmailOrPhone("");
          }}
          disabled={loading}
          className="type-footnote w-full text-center font-medium text-ink-tertiary transition-colors hover:text-ink"
        >
          {usePhone ? "Use email instead" : "Use phone number instead"}
        </button>
      </form>

      <div className="divider my-5" />

      <p className="type-subhead text-center text-ink-tertiary">
        Don&apos;t have an account?{" "}
        <button
          type="button"
          onClick={onSwitch}
          disabled={loading}
          className="font-semibold text-ink hover:underline"
        >
          Create one
        </button>
      </p>
    </Modal>
  );
}
