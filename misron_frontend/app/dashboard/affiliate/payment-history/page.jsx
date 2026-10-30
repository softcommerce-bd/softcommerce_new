"use client";

import { useState, useEffect } from "react";
import { getPaymentHistory, getAffiliateDashboard, createWithdrawRequest } from "@/app/services/affiliateService";
import { toast } from "react-hot-toast";
import WithdrawModal from "@/components/affiliate/WithdrawModal";
import { Wallet, Receipt } from "lucide-react";
import { EmptyState, Skeleton } from "@/app/components/ui";

export default function PaymentHistory() {
  const [payments, setPayments] = useState([]);
  const [balance, setBalance] = useState(0);
  const [loading, setLoading] = useState(true);
  const [showWithdrawModal, setShowWithdrawModal] = useState(false);
  const [withdrawAmount, setWithdrawAmount] = useState("");

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [dashboardRes, paymentsRes] = await Promise.all([
        getAffiliateDashboard(),
        getPaymentHistory(),
      ]);

      if (dashboardRes.result) setBalance(dashboardRes.data.balance || 0);
      if (paymentsRes.result) setPayments(paymentsRes.data?.data || []);
    } catch (error) {
      toast.error("Failed to load data");
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
        fetchData();
      } else {
        toast.error(response.message || "Failed to submit withdraw request");
      }
    } catch (error) {
      toast.error(error.message || "Something went wrong");
    }
  };

  if (loading) {
    return (
      <div>
        <Skeleton className="mb-8 h-7 w-48" />
        <Skeleton className="mb-6 h-32 w-full" rounded="lg" />
        <Skeleton className="h-56 w-full" rounded="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="type-title-2 text-ink">Payment history</h1>
        <p className="type-callout mt-1.5 text-ink-tertiary">
          Payouts you&apos;ve received.
        </p>
      </header>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-xl bg-ink p-6 text-ink-inverse">
          <p className="type-caption text-white/60">Available balance</p>
          <p className="type-display tabular mt-2 text-white">৳ {balance}</p>
        </div>

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

      <section className="card overflow-hidden">
        <header className="border-b border-hairline px-5 py-4">
          <h2 className="type-headline text-ink">Payouts</h2>
        </header>

        {payments.length === 0 ? (
          <EmptyState
            icon={Receipt}
            title="No payments yet"
            description="Payouts you receive will be listed here."
            className="py-14"
          />
        ) : (
          <ul className="divide-y divide-hairline">
            {payments.map((payment) => (
              <li
                key={payment.id}
                className="flex items-center justify-between gap-4 px-5 py-4"
              >
                <div className="min-w-0">
                  <p className="type-subhead font-medium text-ink">
                    {payment.payment_method || "Payout"}
                  </p>
                  <p className="type-caption mt-0.5 text-ink-tertiary">
                    {new Date(payment.created_at).toLocaleDateString()}
                  </p>
                </div>

                <p className="type-subhead tabular shrink-0 font-semibold text-ink">
                  ৳ {payment.amount}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>

      {showWithdrawModal && (
        <WithdrawModal
          balance={balance}
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
