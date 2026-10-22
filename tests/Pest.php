<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/**
 * Decode a recorded API response from tests/Fixtures/<api>/<name>.json.
 */
function fixture(string $api, string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/{$api}/{$name}.json")), true);
}
