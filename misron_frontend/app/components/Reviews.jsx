"use client";

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Star, PenLine, MessageSquare, AlertCircle, Check } from "lucide-react";
import { Button, EmptyState, Pagination, Skeleton } from "./ui";

export default function Reviews({ productId, productData, onReviewSubmitted }) {
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [showReviewForm, setShowReviewForm] = useState(false);
  const [userReview, setUserReview] = useState({ rating: 0, comment: '' });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const router = useRouter();

  // Fetch reviews
  const fetchReviews = async (page = 1) => {
    try {
      setLoading(true);
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/reviews/products/${productId}?page=${page}`);
      const data = await response.json();
      
      if (data.data) {
        setReviews(data.data);
        setCurrentPage(data.meta?.current_page || 1);
        setLastPage(data.meta?.last_page || 1);
      }
    } catch (error) {
      console.error('Error fetching reviews:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (productId) {
      fetchReviews();
    }
  }, [productId]);

  // Check if user can review
  const checkCanReview = async () => {
    try {
      // You might want to create an API endpoint to check if user can review
      // For now, we'll just show the form and let the API handle the validation
      return true;
    } catch (error) {
      return false;
    }
  };

  // Handle review submission
  const handleSubmitReview = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setSubmitting(true);

    try {
      const token = localStorage.getItem('token'); // or however you store your auth token
      
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/reviews/submit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          product_id: productId,
          rating: userReview.rating,
          comment: userReview.comment,
        }),
      });

      const data = await response.json();

      if (data.result) {
        setSuccess(data.message);
        setUserReview({ rating: 0, comment: '' });
        setShowReviewForm(false);
        
        // Refresh reviews
        fetchReviews();
        
        // Call the callback if provided
        if (onReviewSubmitted) {
          onReviewSubmitted();
        }
        
        // Refresh product data to update rating
        router.refresh();
      } else {
        setError(data.message || 'Failed to submit review');
      }
    } catch (error) {
      setError('Network error. Please try again.');
      console.error('Error submitting review:', error);
    } finally {
      setSubmitting(false);
    }
  };

  /**
   * Star rating. In interactive mode each star is a button that sets the
   * rating; otherwise the row is a single labelled graphic, so a screen
   * reader announces "Rated 4 out of 5" instead of five separate stars.
   */
  const renderStars = (rating, interactive = false, onRatingChange = null) => {
    if (interactive) {
      return (
        <div className="flex gap-1">
          {[1, 2, 3, 4, 5].map((i) => (
            <button
              key={i}
              type="button"
              onClick={() => onRatingChange(i)}
              aria-label={`Rate ${i} out of 5`}
              aria-pressed={i <= userReview.rating}
              className="rounded-sm p-0.5 transition-transform duration-fast hover:scale-110"
            >
              <Star
                size={24}
                strokeWidth={1.5}
                className={i <= userReview.rating ? "text-ink" : "text-ink-quaternary"}
                fill={i <= userReview.rating ? "currentColor" : "none"}
              />
            </button>
          ))}
        </div>
      );
    }

    return (
      <span
        className="flex items-center gap-0.5"
        aria-label={`Rated ${rating} out of 5`}
      >
        {[1, 2, 3, 4, 5].map((i) => (
          <Star
            key={i}
            size={14}
            strokeWidth={1.75}
            className={i <= rating ? "text-ink" : "text-ink-quaternary"}
            fill={i <= rating ? "currentColor" : "none"}
            aria-hidden="true"
          />
        ))}
      </span>
    );
  };

  const formatDate = (dateString) =>
    new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });

  return (
    <div>
      {/* ── Summary ────────────────────────────────────────────────────────*/}
      <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="type-title-3 text-ink">Reviews</h2>

          <div className="mt-2 flex items-center gap-2">
            {renderStars(productData.rating || 0)}
            <span className="type-footnote tabular text-ink-tertiary">
              {(productData.rating || 0).toFixed(1)} ·{" "}
              {productData.rating_count || 0}{" "}
              {productData.rating_count === 1 ? "review" : "reviews"}
            </span>
          </div>
        </div>

        {!showReviewForm && (
          <Button
            variant="secondary"
            iconLeft={PenLine}
            onClick={() => setShowReviewForm(true)}
          >
            Write a review
          </Button>
        )}
      </div>

      {/* ── Review form ────────────────────────────────────────────────────*/}
      {showReviewForm && (
        <div className="animate-slide-down mb-10 rounded-xl bg-canvas-sunken p-6">
          <h3 className="type-headline mb-5 text-ink">Write a review</h3>

          {error && (
            <div
              role="alert"
              className="mb-5 flex gap-2.5 rounded-lg bg-danger-tint p-3.5 text-danger"
            >
              <AlertCircle size={17} className="mt-px shrink-0" aria-hidden="true" />
              <p className="type-footnote">{error}</p>
            </div>
          )}

          {success && (
            <div
              role="status"
              className="mb-5 flex gap-2.5 rounded-lg bg-success-tint p-3.5 text-success"
            >
              <Check size={17} className="mt-px shrink-0" aria-hidden="true" />
              <p className="type-footnote">{success}</p>
            </div>
          )}

          <form onSubmit={handleSubmitReview}>
            <div className="mb-5">
              <span className="field-label">Your rating</span>
              {renderStars(5, true, (rating) =>
                setUserReview({ ...userReview, rating }),
              )}
              {userReview.rating === 0 && (
                <p className="field-hint">Tap a star to rate this product.</p>
              )}
            </div>

            <div className="mb-5">
              <label htmlFor="review-comment" className="field-label">
                Your review
              </label>
              <textarea
                id="review-comment"
                value={userReview.comment}
                onChange={(e) =>
                  setUserReview({ ...userReview, comment: e.target.value })
                }
                rows="4"
                className="field field-textarea"
                placeholder="What did you think of it?"
              />
            </div>

            <div className="flex flex-wrap gap-2.5">
              <Button type="submit" loading={submitting} disabled={userReview.rating === 0}>
                {submitting ? "Posting" : "Post review"}
              </Button>

              <Button
                type="button"
                variant="ghost"
                onClick={() => {
                  setShowReviewForm(false);
                  setUserReview({ rating: 0, comment: "" });
                  setError("");
                  setSuccess("");
                }}
              >
                Cancel
              </Button>
            </div>
          </form>
        </div>
      )}

      {/* ── Review list ────────────────────────────────────────────────────*/}
      {loading ? (
        <div className="space-y-6">
          {[0, 1, 2].map((i) => (
            <div key={i} className="space-y-2">
              <Skeleton className="h-3.5 w-32" />
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-3 w-full" />
              <Skeleton className="h-3 w-4/5" />
            </div>
          ))}
        </div>
      ) : reviews.length > 0 ? (
        <>
          <ul className="divide-y divide-hairline">
            {reviews.map((review) => (
              <li key={review.id} className="py-5 first:pt-0">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <span className="type-subhead font-semibold text-ink">
                    {review.user?.name || "Anonymous"}
                  </span>
                  {renderStars(review.rating)}
                  <span className="type-caption text-ink-tertiary">
                    {formatDate(review.created_at)}
                  </span>
                </div>

                <p className="type-callout mt-2.5 text-ink-secondary">
                  {review.comment}
                </p>
              </li>
            ))}
          </ul>

          <Pagination
            currentPage={currentPage}
            lastPage={lastPage}
            onPageChange={fetchReviews}
            className="mt-8"
          />
        </>
      ) : (
        <EmptyState
          icon={MessageSquare}
          title="No reviews yet"
          description="Be the first to share what you think of this product."
          action={
            showReviewForm
              ? undefined
              : { label: "Write a review", onClick: () => setShowReviewForm(true) }
          }
          className="py-12"
        />
      )}
    </div>
  );
}
