<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\FunnelPreviewLink;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Secret links to a funnel's draft, for showing work in progress to a client
 * or a colleague who has no account.
 *
 * - The link carries a random 40-character secret. Only its SHA-256 hash is
 *   stored, so a copy of the database cannot open a preview.
 * - A link belongs to one funnel, has an expiry, and can be revoked at any time.
 * - It shows the draft as it is when opened, so reviewers see edits without a
 *   new link, and it never places orders (the page runs in preview mode).
 */
class FunnelPreviewService
{
    private $audit;

    public function __construct(FunnelAudit $audit)
    {
        $this->audit = $audit;
    }

    /**
     * @return array{0: FunnelPreviewLink, 1: string} the stored link and the secret, which is never shown again
     */
    public function create(Funnel $funnel, User $user, ?int $hours = null, ?string $label = null): array
    {
        if ($funnel->status === Funnel::STATUS_ARCHIVED) {
            throw FunnelException::state('This funnel is archived. Restore it before sharing a preview.');
        }

        $max = (int) config('funnel.preview.max_days') * 24;
        $hours = $hours === null ? (int) config('funnel.preview.default_hours') : $hours;
        if ($hours < 1 || $hours > $max) {
            throw new FunnelException("A preview link can last from 1 hour to {$max} hours.", 'invalid_expiry', 422);
        }

        $active = FunnelPreviewLink::where('funnel_id', $funnel->id)->active()->count();
        if ($active >= (int) config('funnel.preview.max_active_links')) {
            throw new FunnelException('This funnel already has the maximum number of active preview links. Revoke one first.', 'too_many_links', 422);
        }

        $secret = Str::random(40);
        $link = FunnelPreviewLink::create([
            'funnel_id' => $funnel->id,
            'token_hash' => self::hash($secret),
            'label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 120) : null,
            'expires_at' => now()->addHours($hours),
            'created_by' => $user->id,
        ]);

        $this->audit->log($funnel, 'preview_link_created', $user, ['link' => $link->id, 'hours' => $hours]);

        return [$link, $secret];
    }

    /** @return FunnelPreviewLink[] newest first, including expired and revoked ones from the last 30 days */
    public function list(Funnel $funnel)
    {
        return FunnelPreviewLink::where('funnel_id', $funnel->id)
            ->where('created_at', '>', now()->subDays(30))
            ->orderByDesc('id')
            ->get();
    }

    public function revoke(Funnel $funnel, int $linkId, User $user): FunnelPreviewLink
    {
        $link = FunnelPreviewLink::where('funnel_id', $funnel->id)->find($linkId);
        if (!$link) {
            throw new FunnelException('Preview link not found.', 'not_found', 404);
        }
        if ($link->revoked_at === null) {
            $link->revoked_at = now();
            $link->save();
            $this->audit->log($funnel, 'preview_link_revoked', $user, ['link' => $link->id]);
        }

        return $link;
    }

    /**
     * The funnel a secret opens, or null when the secret is unknown, expired,
     * revoked, for another funnel, or the funnel is archived or deleted.
     * One answer for every failure, so a guesser learns nothing.
     */
    public function resolve(string $slug, string $secret): ?Funnel
    {
        if (!preg_match('/^[A-Za-z0-9]{40}$/', $secret) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }

        $link = FunnelPreviewLink::active()->where('token_hash', self::hash($secret))->first();
        if (!$link) {
            return null;
        }

        $funnel = Funnel::where('slug', $slug)->where('id', $link->funnel_id)->first();
        if (!$funnel || $funnel->status === Funnel::STATUS_ARCHIVED) {
            return null;
        }

        // Counting every open would turn each page view into a write.
        if ($link->last_used_at === null || $link->last_used_at->lt(now()->subMinute())) {
            $link->forceFill(['last_used_at' => now(), 'use_count' => $link->use_count + 1])->save();
        }

        return $funnel;
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
