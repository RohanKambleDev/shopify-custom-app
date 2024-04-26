<?php

namespace App\Services\Veeva;

interface VaultInterface
{
    public function makeHeadersForApiCall(array $data = []): array;

    public function makeSessionId(): array;
}