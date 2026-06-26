<?php

namespace Syon\AuditSdk\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Syon\AuditSdk\View\Concerns\FetchesNotice;

/**
 * Renders the notice's first-layer summary — the short, plain statement to show on
 * the form itself, at the point of collection:
 *
 *     <x-audit-notice-summary point="newsletter_signup" />
 *
 * Authored and managed on the platform alongside the full notice, so it stays in sync
 * and versioned with it. Renders nothing when no summary is set. Pair it with your own
 * trigger to the full notice (a dialog, a modal, …).
 */
class AuditNoticeSummary extends Component
{
    use FetchesNotice;

    public function __construct(public string $point) {}

    public function render(): View
    {
        return view('audit-sdk::notice-summary', ['summary' => $this->resolveNotice($this->point)['summary']]);
    }
}
