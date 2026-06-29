<?php

namespace Syon\AuditSdk\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Syon\AuditSdk\View\Concerns\FetchesNotice;

/**
 * Renders every in-force notice for the project as a stack of sections — the
 * comprehensive layer, for a consolidated privacy policy:
 *
 *     <x-audit-notices />
 *
 * Each section is one processing activity's approved Article 13/14 copy, kept in
 * lockstep with what's adopted on the platform. Cached and fail-soft. Wrap it with
 * your own controller-level content (identity, DPO, rights, how to complain) — the
 * platform owns the per-activity processing copy, not the controller boilerplate.
 */
class AuditNotices extends Component
{
    use FetchesNotice;

    public function render(): View
    {
        return view('audit-sdk::notices', ['notices' => $this->resolveNoticeList()]);
    }
}
