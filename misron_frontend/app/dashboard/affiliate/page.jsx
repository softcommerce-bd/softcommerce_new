"use client";

import { useState, useEffect } from "react";
import {
  getAffiliateDashboard,
  getAffiliateStats,
  getAffiliateLogs,
  createWithdrawRequest,
} from "@/app/services/affiliateService";
import { toast } from "react-hot-toast";
import WithdrawModal from "@/components/affiliate/WithdrawModal";
import Link from "next/link";
import { Settings, Wallet, Copy, Users } from "lucide-react";
import { Button, EmptyState, Skeleton } from "@/app/components/ui";

export default function AffiliateDashboard() {
  const [dashboard, setDashboard] = useState(null);
  const [stats, setStats] = useState(null);
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statsType, setStatsType] = useState("");
  const [showWithdrawModal, setShowWithdrawModal] = useState(false);
  const [withdrawAmount, setWithdrawAmount] = useState("");
  // console.log("logs value is ", logs);
  useEffect(() => {
    fetchDashboardData();
  }, [statsType]);

  const fetchDashboardData = async () => {
    setLoading(true);
    try {
      const [dashboardRes, statsRes, logsRes] = await Promise.all([
        getAffiliateDashboard(),
        getAffiliateStats(statsType),
        getAffiliateLogs(),
      ]);

      // console.log("Dashboard Response:", dashboardRes);
      // console.log("Stats Response:", statsRes);
      // console.log("Logs Response:", logsRes);

      if (dashboardRes.result) setDashboard(dashboardRes.data);
      if (statsRes.result) setStats(statsRes.data);
      if (logsRes.result) setLogs(logsRes.data?.data || []);
    } catch (error) {
      toast.error("Failed to load affiliate data");

      console.log("error:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleWithdrawRequest = async () => {
    try {
      const response = await createWithdrawRequest(withdrawAmount);
      if (response.result) {
        toast.success("Withdraw request submitted successfully!");
        setShowWithdrawModal(false);
        setWithdrawAmount("");
        fetchDashboardData();
      } else {
        toast.error(response.message || "Failed to submit withdraw request");
      }
    } catch (error) {
      toast.error(error.message || "Something went wrong");
    }
  };

  const copyReferralUrl = () => {
    const referralUrl = `${window.location.origin}/signup?ref=${dashboard?.referral_code}`;
    navigator.clipboard.writeText(referralUrl);
    toast.success("Referral URL copied to clipboard!");
  };

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-8 h-7 w-52" />
        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-32 w-full" rounded="lg" />
          ))}
        </div>
        <Skeleton className="h-40 w-full" rounded="lg" />
      </div>
    );
  }

  const referralUrl = `${
    typeof window !== "undefined" ? window.location.origin : ""
  }/signup?ref=${dashboard?.referral_code || ""}`;

  const statFilters = [
    { label: "All time", value: "" },
    { label: "Today", value: "today" },
    { label: "7 days", value: "7" },
    { label: "30 days", value: "30" },
  ];

  const statTiles = [
    { label: "Clicks", value: stats?.total_clicks || 0 },
    { label: "Orders", value: stats?.total_orders || 0 },
    { label: "Delivered", value: stats?.total_delivered || 0 },
    { label: "Cancelled", value: stats?.total_cancelled || 0 },
  ];

  return (
    <div className="space-y-6">
      <header>
        <h1 className="type-title-2 text-ink">Affiliate</h1>
        <p className="type-callout mt-1.5 text-ink-tertiary">
          Track your referrals and earnings.
        </p>
      </header>

      {/* ── Balance and actions ────────────────────────────────────────────*/}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        {/* Balance is the one dark panel on the page, so the number a user
            came to see is the first thing they find. */}
        <div className="rounded-xl bg-ink p-6 text-ink-inverse">
          <p className="type-caption text-white/60">Available balance</p>
          <p className="type-display tabular mt-2 text-white">
            ৳ {dashboard?.balance || 0}
          </p>
        </div>

        <Link
          href="/dashboard/affiliate/payment-settings"
          className="card card-interactive flex flex-col items-start justify-between gap-4 p-6"
        >
          <Settings size={20} strokeWidth={1.5} className="text-ink-secondary" aria-hidden="true" />
          <div>
            <p className="type-subhead font-semibold text-ink">Payout settings</p>
            <p className="type-caption mt-1 text-ink-tertiary">
              Where your withdrawals are sent
            </p>
          </div>
        </Link>

        <button
          type="button"
          onClick={() => setShowWithdrawModal(true)}
          className="card card-interactive flex flex-col items-start justify-between gap-4 p-6 text-left"
        >
          <Wallet size={20} strokeWidth={1.5} className="text-ink-secondary" aria-hidden="true" />
          <div>
            <p className="type-subhead font-semibold text-ink">Request withdrawal</p>
            <p className="type-caption mt-1 text-ink-tertiary">
              Move your balance to your payout account
            </p>
          </div>
        </button>
      </div>

      {/* ── Referral link ──────────────────────────────────────────────────*/}
      <section className="card p-5">
        <h2 className="type-headline mb-1 text-ink">Your referral link</h2>
        <p className="type-footnote mb-4 text-ink-tertiary">
          Share this link — you earn on orders placed by anyone who signs up
          through it.
        </p>

        <div className="flex flex-col gap-2.5 sm:flex-row">
          <label htmlFor="referral-url" className="sr-only-focusable">
            Referral URL
          </label>
          <input
            id="referral-url"
            readOnly
            value={referralUrl}
            onFocus={(e) => e.target.select()}
            className="field flex-1 bg-canvas-sunken text-[0.8125rem]"
          />
          <Button
            variant="secondary"
            className="shrink-0"
            iconLeft={Copy}
            onClick={copyReferralUrl}
          >
            Copy
          </Button>
        </div>
      </section>

      {/* ── Stats ──────────────────────────────────────────────────────────*/}
      <section className="card overflow-hidden">
        <header className="flex flex-col gap-3 border-b border-hairline px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <h2 className="type-headline text-ink">Statistics</h2>

          <div
            role="tablist"
            aria-label="Statistics period"
            className="inline-flex gap-1 rounded-full bg-canvas-sunken p-1"
          >
            {statFilters.map((filter) => {
              const isSelected = statsType === filter.value;
              return (
                <button
                  key={filter.value}
                  type="button"
                  role="tab"
                  aria-selected={isSelected}
                  onClick={() => setStatsType(filter.value)}
                  className={`whitespace-nowrap rounded-full px-3 py-1.5 text-[0.75rem] font-semibold transition-all duration-fast ease-standard ${
                    isSelected
                      ? "bg-surface text-ink shadow-e1"
                      : "text-ink-tertiary hover:text-ink"
                  }`}
                >
                  {filter.label}
                </button>
              );
            })}
          </div>
        </header>

        <div className="grid grid-cols-2 divide-hairline sm:grid-cols-4 sm:divide-x">
          {statTiles.map((tile) => (
            <div key={tile.label} className="px-5 py-6 text-center">
              <p className="type-title-2 tabular text-ink">{tile.value}</p>
              <p className="type-caption mt-1 text-ink-tertiary">{tile.label}</p>
            </div>
          ))}
        </div>
      </section>

      {/* ── Earnings ───────────────────────────────────────────────────────*/}
      <section className="card overflow-hidden">
        <header className="border-b border-hairline px-5 py-4">
          <h2 className="type-headline text-ink">Earnings</h2>
        </header>

        {logs.length === 0 ? (
          <EmptyState
            icon={Users}
            title="No earnings yet"
            description="Once someone orders through your link, it will show up here."
            className="py-14"
          />
        ) : (
          <ul className="divide-y divide-hairline">
            {logs.map((log) => (
              <li key={log.id} className="flex items-start justify-between gap-4 px-5 py-4">
                <div className="min-w-0">
                  <p className="type-subhead font-medium text-ink">
                    {log.user?.name || "Unknown user"}
                  </p>

                  <p className="type-caption mt-1 text-ink-tertiary">
                    {new Date(log.created_at).toLocaleDateString()}
                    {log.order_id && ` · Order ${log.order_id}`}
                    {log.affiliate_type &&
                      ` · ${log.affiliate_type.replace(/_/g, " ")}`}
                  </p>

                  {log.order_detail?.product && (
                    <a
                      href={`/product/${log.order_detail.product.slug}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="type-caption mt-1 inline-block text-ink-secondary underline underline-offset-2 hover:text-ink"
                    >
                      {log.order_detail.product.name}
                    </a>
                  )}
                </div>

                <p className="type-subhead tabular shrink-0 font-semibold text-success">
                  +৳ {log.amount}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>

      {showWithdrawModal && (
        <WithdrawModal
          balance={dashboard?.balance || 0}
          amount={withdrawAmount}
          setAmount={setWithdrawAmount}
          onClose={() => {
            setShowWithdrawModal(false);
            setWithdrawAmount("");
          }}
          onSubmit={handleWithdrawRequest}
        />
      )}
    </div>
  );
}
