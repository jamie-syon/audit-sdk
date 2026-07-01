<?php

namespace Syon\AuditSdk\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Syon\AuditSdk\View\Concerns\FetchesNotice;

/**
 * Renders the data controller identity + contact details a notice must state
 * (Article 13(1)(a)/(b)) — the controller(s), joint-controller arrangement, DPO, and
 * UK/EU representative(s). Standalone, for a footer, contact page, or a single form's
 * notice:
 *
 *     <x-audit-controller />               {{-- heading at <h2> --}}
 *     <x-audit-controller :level="3" />    {{-- heading at <h3> --}}
 *     <x-audit-controller heading="Who we are" />
 *
 * Cached and fail-soft. Renders nothing when no controller has been captured.
 */
class AuditController extends Component
{
    use FetchesNotice;

    public function __construct(
        public int $level = 2,
        public string $heading = 'Who we are and how to contact us',
    ) {}

    public function render(): View
    {
        return view('audit-sdk::controller', [
            'controller' => $this->resolveController(),
            'level' => $this->level,
            'heading' => $this->heading,
        ]);
    }
}
