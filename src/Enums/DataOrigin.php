<?php

namespace Syon\AuditSdk\Enums;

/** Where an activity's records originate, per the ingest contract. */
enum DataOrigin: string
{
    case Direct = 'direct';
    case Indirect = 'indirect';
    case Mixed = 'mixed';
}
