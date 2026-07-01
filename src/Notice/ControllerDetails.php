<?php

namespace Syon\AuditSdk\Notice;

/**
 * The data controller identity + contact details a privacy notice must state
 * (Article 13(1)(a)/(b)): the controller(s), and optional DPO and representative(s).
 * Served in the /notices response and rendered by the notice components.
 */
class ControllerDetails
{
    /**
     * @param  list<array<string, string>>  $controllers
     * @param  array<string, string>|null  $dpo
     * @param  list<array<string, string>>  $representatives
     */
    public function __construct(
        public readonly array $controllers,
        public readonly ?string $jointArrangement,
        public readonly ?array $dpo,
        public readonly array $representatives,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $filled = static fn (?array $party): bool => is_array($party)
            && trim((string) ($party['email'] ?? $party['name'] ?? '')) !== '';

        $joint = trim((string) ($data['joint_arrangement'] ?? ''));

        return new self(
            controllers: array_values(array_filter($data['controllers'] ?? [], 'is_array')),
            jointArrangement: $joint !== '' ? $joint : null,
            dpo: $filled($data['dpo'] ?? null) ? $data['dpo'] : null,
            representatives: array_values(array_filter($data['representatives'] ?? [], $filled)),
        );
    }

    public function isEmpty(): bool
    {
        return $this->controllers === [];
    }
}
