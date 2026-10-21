<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

final class QueueErrorDisposition
{
    public const RETRY = 'retry';

    public const DROP_PERMANENT = 'drop_permanent';

    public const TREAT_AS_SUCCESS = 'treat_as_success';

    public const PAUSE_TENANT = 'pause_tenant';
}
