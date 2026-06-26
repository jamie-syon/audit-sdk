<?php

namespace Syon\AuditSdk\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Syon\AuditSdk\View\Concerns\FetchesNotice;

/**
 * Renders the platform's in-force Article 13 notice content at a collection point:
 *
 *     <x-audit-notice point="newsletter_signup" />
 *
 * The reusable atom — drop it on the page, or inside any modal (a native dialog, a
 * FluxUI modal, a Bootstrap modal) to keep the chrome styled by the host app.
 */
class AuditNotice extends Component
{
    use FetchesNotice;

    public function __construct(public string $point) {}

    public function render(): View
    {
        return view('audit-sdk::notice', ['html' => $this->resolveNotice($this->point)['html']]);
    }
}
