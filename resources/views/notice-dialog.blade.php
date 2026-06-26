{{-- A trigger link + native <dialog>. No frontend framework required. The notice HTML
     is authored and sealed on the platform by the project's own people (trusted). --}}
@if (! empty($html))
    <button type="button" {{ $attributes->merge(['class' => 'audit-notice-trigger']) }} data-audit-notice-trigger="{{ $point }}">
        {{ $trigger }}
    </button>

    {{-- No nested <form> here: this component is meant to sit inside a form (notices
         live at the point of collection), so closing is handled in JS, not method="dialog". --}}
    <dialog class="audit-notice-dialog" data-audit-notice-dialog="{{ $point }}">
        <div class="audit-notice-content">{!! $html !!}</div>
        <div class="audit-notice-actions">
            <button type="button" class="audit-notice-close" data-audit-notice-close>Close</button>
        </div>
    </dialog>

    @once
        <style>
            .audit-notice-trigger { background: none; border: 0; padding: 0; margin: 0; font: inherit; color: inherit; cursor: pointer; text-decoration: underline; }
            .audit-notice-dialog { max-width: 32rem; width: calc(100% - 2rem); border: 1px solid #e4e4e7; border-radius: .75rem; padding: 1.5rem; color: #18181b; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,.15); }
            .audit-notice-dialog::backdrop { background: rgba(0,0,0,.45); }
            .audit-notice-content { font-size: .875rem; line-height: 1.55; }
            .audit-notice-actions { margin: 1rem 0 0; text-align: right; }
            .audit-notice-close { font: inherit; cursor: pointer; border: 1px solid #d4d4d8; background: #fff; border-radius: .5rem; padding: .375rem .85rem; }
        </style>
        <script>
            // Delegated + CSP-safe. Open on the trigger, close on the close button or a
            // backdrop click; ESC-to-close is handled natively by <dialog>.
            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-audit-notice-trigger]');
                if (trigger) {
                    var point = trigger.getAttribute('data-audit-notice-trigger');
                    var dialog = document.querySelector('[data-audit-notice-dialog="' + point + '"]');
                    if (dialog && typeof dialog.showModal === 'function') {
                        event.preventDefault();
                        dialog.showModal();
                    }
                    return;
                }
                var closer = event.target.closest('[data-audit-notice-close]');
                if (closer) {
                    var owning = closer.closest('dialog');
                    if (owning) { owning.close(); }
                    return;
                }
                // A click on the dialog element itself (its backdrop, not its content) closes it.
                if (event.target.classList && event.target.classList.contains('audit-notice-dialog')) {
                    event.target.close();
                }
            });
        </script>
    @endonce
@endif
