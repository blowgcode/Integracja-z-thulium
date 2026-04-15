<?php

declare(strict_types=1);

namespace ThuliumBridge\Infrastructure\Thulium;

final class TenantCustomerApiAssumptions
{
    public const SEARCH_ENDPOINT = '/customers';
    public const CREATE_ENDPOINT = '/customers';
    public const UPDATE_ENDPOINT_PREFIX = '/customers/';

    /**
     * Tenant-specific assumptions:
     * - GET /customers?external_id=...
     * - GET /customers?phone=...
     * - POST /customers
     * - PUT /customers/{id}
     */
}
