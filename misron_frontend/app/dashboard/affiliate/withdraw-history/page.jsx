"use client";

/**
 * Affiliate withdrawal requests.
 *
 * Restyled on the design system: the table becomes a list, the status pills
 * use the shared Badge tones, and an empty history renders an empty state
 * rather than a "No withdrawal history found" cell.
 *
 * Fetching and the numeric status mapping (0 pending, 1 approved, 2 rejected)
 * are unchanged.
 */

import { useState, useEffect } from "react";
import { getWithdrawHistory } from "@/app/services/affiliateService";
import { toast } from "react-hot-toast";
import { Banknote } from "lucide-react";

import { Badge, EmptyState, Skeleton } from "@/app/components/ui";

const STATUS = {
  0: { label: "Pending", tone: "warning" },
  1: { label: "Approved", tone: "success" },
  2: { label: "Rejected", tone: "danger" },
};

export default function WithdrawHistory() {
  const [withdrawals, setWithdrawals] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchWithdrawHistory();
  }, []);

  const fetchWithdrawHistory = async () => {
    try {
      const response = await getWithdrawHistory();
      if (response.result) {
        setWithdrawals(response.data?.data || []);
      }
    } catch (error) {
      toast.error("Failed to load withdraw history");
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-8 h-7 w-48" />
        <Skeleton className="h-56 w-full" rounded="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="type-title-2 text-ink">Withdrawals</h1>
        <p className="type-callout mt-1.5 text-ink-tertiary">
          Requests you&apos;ve made and where they stand.
        </p>
      </header>

      <section className="card overflow-hidden">
        {withdrawals.length === 0 ? (
          <EmptyState
            icon={Banknote}
            title="No withdrawals yet"
            description="Requests you make from the affiliate page will appear here."
            action={{ label: "Go to affiliate", href: "/dashboard/affiliate" }}
            className="py-14"
          />
        ) : (
          <ul className="divide-y divide-hairline">
            {withdrawals.map((withdrawal) => {
              const status = STATUS[withdrawal.status] ?? {
                label: "Unknown",
                tone: "neutral",
              };

              return (
                <li
                  key={withdrawal.id}
                  className="flex items-center justify-between gap-4 px-5 py-4"
                >
                  <div className="min-w-0">
                    <p className="type-subhead tabular font-semibold text-ink">
                      ৳ {withdrawal.amount}
                    </p>
                    <p className="type-caption mt-0.5 text-ink-tertiary">
                      {new Date(withdrawal.created_at).toLocaleDateString()}
                    </p>
                  </div>

                  <Badge tone={status.tone}>{status.label}</Badge>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </div>
  );
}
