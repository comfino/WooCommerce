<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

final class SubmitResult
{
    public const SENT_IMMEDIATELY = 'sent_immediately';

    public const QUEUED = 'queued';

    public const DROPPED_PERMANENT = 'dropped_permanent';
}
