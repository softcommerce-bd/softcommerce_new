"use client";

/**
 * Sign-up dialog.
 *
 * Rebuilt on the shared Modal, Input and Button, matching LoginModal so the
 * two read as one flow when switching between them.
 *
 * All referral-code resolution, validation and signup request behaviour is
 * carried over unchanged.
 */

import { useState, useEffect } from "react";
import { useSearchParams } from "next/navigation";
import toast from "react-hot-toast";
import { Mail, Phone, Lock, User, AlertCircle, Gift } from "lucide-react";

import { Modal, Input, Button } from "../ui";

function getCookie(name) {
  if (typeof document === "undefined") return null;
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(";").shift();
  return null;
}

export default function RegisterModal({ open, onClose, onSwitch, onRegisterSuccess }) {
  const searchParams = useSearchParams();
  const [usePhone, setUsePhone] = useState(false);
  const [name, setName] = useState("");
  const [emailOrPhone, setEmailOrPhone] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPass, setConfirmPass] = useState("");
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState([]);
  const [referralCode, setReferralCode] = useState(null);

  // Resolve a referral code from the URL, then cookie, then session, then
  // local storage — a referred visitor may arrive on any of these paths.
  useEffect(() => {
    if (!open) return;

    let refCode = searchParams.get("ref") || searchParams.get("referral_code");

    if (!refCode) refCode = getCookie("referral_code");
    if (!refCode) refCode = sessionStorage.getItem("referral_code");
    if (!refCode) refCode = localStorage.getItem("referral_code");

    if (refCode) {
      setReferralCode(refCode);

      if (!getCookie("referral_code")) {
        const maxAge = 30 * 24 * 60 * 60;
        document.cookie = `referral_code=${refCode}; max-age=${maxAge}; path=/; SameSite=Lax`;
      }
      sessionStorage.setItem("referral_code", refCode);
    }
  }, [open, searchParams]);

  const handleRegister = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrors([]);

    if (!name || !emailOrPhone || !password || !confirmPass) {
      setErrors(["Please fill in all fields"]);
      setLoading(false);
      return;
    }

    if (password !== confirmPass) {
      setErrors(["Password confirmation does not match"]);
      setLoading(false);
      return;
    }

    if (password.length < 6) {
      setErrors(["Password must be at least 6 characters"]);
      setLoading(false);
      return;
    }

    if (usePhone) {
      if (!/^\d+$/.test(emailOrPhone)) {
        setErrors(["Phone number must contain only digits"]);
        setLoading(false);
        return;
      }
    } else {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(emailOrPhone)) {
        setErrors(["Please enter a valid email address"]);
        setLoading(false);
        return;
      }
    }

    const payload = {
      name: name,
      email_or_phone: emailOrPhone,
      password: password,
      password_confirmation: confirmPass,
      register_by: usePhone ? "phone" : "email",
    };

    if (referralCode) payload.referral_code = referralCode;

    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/auth/signup`, {
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

        // Clear referral from every store now that it has been consumed.
        document.cookie =
          "referral_code=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        sessionStorage.removeItem("referral_code");
        localStorage.removeItem("referral_code");
        setReferralCode(null);

        toast.success(`Welcome ${data.user?.name || ""}!`);

        if (onRegisterSuccess) onRegisterSuccess(data.user);

        onClose();
        setName("");
        setEmailOrPhone("");
        setPassword("");
        setConfirmPass("");
      } else {
        if (data.message && Array.isArray(data.message)) {
          setErrors(data.message);
          toast.error(data.message[0]);
        } else if (data.message && typeof data.message === "string") {
          setErrors([data.message]);
          toast.error(data.message);
        } else {
          toast.error("Registration failed. Please try again.");
        }
      }
    } catch (err) {
      console.error("Registration error:", err);
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
      title="Create your account"
      description="Save your details for faster checkout and order tracking."
    >
      {referralCode && (
        <div className="mb-5 flex items-center gap-2.5 rounded-lg bg-success-tint p-3.5 text-success">
          <Gift size={17} className="shrink-0" aria-hidden="true" />
          <p className="type-footnote font-medium">
            You&apos;ve been referred by a friend
          </p>
        </div>
      )}

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

      <form onSubmit={handleRegister} className="space-y-4">
        <Input
          label="Full name"
          icon={User}
          placeholder="Your name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          autoComplete="name"
          required
          disabled={loading}
        />

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

        <Input
          label="Password"
          type="password"
          icon={Lock}
          placeholder="At least 6 characters"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          autoComplete="new-password"
          minLength={6}
          required
          disabled={loading}
        />

        <Input
          label="Confirm password"
          type="password"
          icon={Lock}
          placeholder="Re-enter your password"
          value={confirmPass}
          onChange={(e) => setConfirmPass(e.target.value)}
          autoComplete="new-password"
          minLength={6}
          required
          disabled={loading}
          // Mismatch is surfaced as the field is filled, not only on submit.
          error={
            confirmPass && password !== confirmPass ? "Passwords do not match" : undefined
          }
        />

        <Button type="submit" size="lg" fullWidth loading={loading}>
          {loading ? "Creating account" : "Create account"}
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
        Already have an account?{" "}
        <button
          type="button"
          onClick={onSwitch}
          disabled={loading}
          className="font-semibold text-ink hover:underline"
        >
          Sign in
        </button>
      </p>
    </Modal>
  );
}
