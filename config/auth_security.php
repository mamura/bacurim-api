<?php

$proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
if (in_array('*', $proxies, true)) {
    throw new InvalidArgumentException('Configure explicit trusted proxy addresses.');
}

return ['trusted_proxies' => $proxies, 'absolute_lifetime_seconds' => 43200];
