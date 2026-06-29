<?php

namespace Syon\AuditSdk\Facades;

use Illuminate\Support\Facades\Facade;
use Syon\AuditSdk\Client\AuditClient;

/**
 * @method static \Syon\AuditSdk\Responses\IngestResult push(\Syon\AuditSdk\Payload\PushPayload $payload)
 * @method static \Syon\AuditSdk\Catalogue\Catalogue catalogue()
 * @method static \Syon\AuditSdk\Notice\Notice|null notice(string $collectionPoint)
 * @method static list<\Syon\AuditSdk\Notice\PolicyNotice> notices()
 * @method static string projectId()
 *
 * @see \Syon\AuditSdk\Client\AuditClient
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditClient::class;
    }
}
