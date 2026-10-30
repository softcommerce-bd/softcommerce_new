"use client";

/**
 * Affiliate withdrawal request dialog.
 *
 * Rebuilt on the shared Modal, so it traps focus, closes on Escape and locks
 * page scroll. The previous overlay's scrim used `bg-gray bg-opacity-50` —
 * `bg-gray` is not a Tailwind class and `bg-opacity-*` was removed in v4 — so
 * it rendered fully transparent and the dialog floated over live page content.
 *
 * The amount field, its bounds and the submit callback are unchanged.
 */

import { Modal, Button } from "@/app/components/ui";

export default function WithdrawModal({ balance, amount, setAmount, onClose, onSubmit }) {
  const numericAmount = Number(amount);
  const exceedsBalance = numericAmount > balance;
  const isValid = amount && numericAmount > 0 && !exceedsBalance;

  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title="Request withdrawal"
      description={`Available balance Tk ${balance}`}
      footer={
        <div className="flex justify-end gap-2.5">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={onSubmit} disabled={!isValid}>
            Request
          </Button>
        </div>
      }
    >
      <div>
        <label htmlFor="withdraw-amount" className="field-label">
          Amount
        </label>

        <div className="relative">
          <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-tertiary">
            Tk
          </span>
          <input
            id="withdraw-amount"
            type="number"
            name="amount"
            min="1"
            max={balance}
            placeholder="0"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            className={`field tabular pl-11 ${exceedsBalance ? "field-error" : ""}`}
            aria-invalid={exceedsBalance || undefined}
            required
          />
        </div>

        {exceedsBalance ? (
          <p role="alert" className="field-error-text">
            That&apos;s more than your available balance.
          </p>
        ) : (
          <p className="field-hint">
            You can withdraw up to Tk {balance}.
          </p>
        )}
      </div>
    </Modal>
  );
}
