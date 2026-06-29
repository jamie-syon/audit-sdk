<?php

namespace Syon\AuditSdk\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Syon\AuditSdk\View\Concerns\FetchesNotice;

/**
 * Renders every in-force notice for the project as a stack of sections — the
 * comprehensive layer, for a consolidated privacy policy:
 *
 *     <x-audit-notices />              {{-- activity names as <h2>, body titles as <h3> --}}
 *     <x-audit-notices :level="3" />   {{-- nest deeper: activity <h3>, body titles <h4> --}}
 *
 * Each section is one processing activity's approved Article 13/14 copy. The activity
 * name is rendered at $level and the copy's own headings are pushed down to sit beneath
 * it, so the document outline stays correct wherever you embed it. Cached and fail-soft.
 * Wrap it with your own controller-level content (identity, DPO, rights, complaints).
 */
class AuditNotices extends Component
{
    use FetchesNotice;

    public function __construct(public int $level = 2) {}

    public function render(): View
    {
        // The authored copy's headings start at <h2>; push them below the activity
        // heading so they nest rather than collide with it.
        $shift = max(0, $this->level - 1);

        $notices = array_map(function (array $notice) use ($shift): array {
            $notice['html'] = $this->shiftHeadings($notice['html'], $shift);

            return $notice;
        }, $this->resolveNoticeList());

        return view('audit-sdk::notices', ['notices' => $notices, 'level' => $this->level]);
    }

    /** Push every heading in the copy down by $by levels (capped at h6). */
    private function shiftHeadings(string $html, int $by): string
    {
        if ($by < 1) {
            return $html;
        }

        // Highest level first, so a shifted heading is never matched again.
        for ($from = 6; $from >= 1; $from--) {
            $to = min(6, $from + $by);
            $html = (string) preg_replace('/<(\/?)[hH]'.$from.'\b/', '<$1h'.$to, $html);
        }

        return $html;
    }
}
