<?php

namespace Syon\AuditSdk\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Syon\AuditSdk\View\Concerns\FetchesNotice;

/**
 * Batteries-included, framework-agnostic notice: a trigger link plus a native HTML
 * <dialog> containing the notice — no Alpine, Livewire, Bootstrap or Tailwind needed.
 *
 *     <x-audit-notice-dialog point="newsletter_signup" trigger="Privacy notice" />
 *
 * The <dialog> element provides the backdrop, ESC-to-close and focus handling natively.
 * Ships scoped, publishable CSS so it looks right out of the box on any stack.
 */
class AuditNoticeDialog extends Component
{
    use FetchesNotice;

    public function __construct(
        public string $point,
        public string $trigger = 'Privacy notice',
    ) {}

    public function render(): View
    {
        return view('audit-sdk::notice-dialog', ['html' => $this->noticeHtml($this->point)]);
    }
}
