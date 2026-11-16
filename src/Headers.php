<?php

namespace Applu\CentralBridge;

final class Headers
{
    public const TIMESTAMP = 'X-Central-Timestamp';

    public const SIGNATURE = 'X-Central-Signature';

    public const REQUEST_ID = 'X-Central-Request-Id';

    public const ACTOR = 'X-Central-Actor';

    public const PRODUCT = 'X-Central-Product';

    public const IDEMPOTENCY_KEY = 'X-Idempotency-Key';

    public const IDEMPOTENCY_REPLAYED = 'X-Idempotency-Replayed';
}
