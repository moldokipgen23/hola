<?php

namespace Tests\Unit;

use App\Support\AgentTaskInputSanitizer;
use PHPUnit\Framework\TestCase;

class AgentTaskInputSanitizerTest extends TestCase
{
    public function test_it_removes_sensitive_values_recursively(): void
    {
        $input = [
            '_token' => 'csrf-value',
            'query' => 'restaurants',
            'nested' => [
                'Authorization' => 'Bearer secret',
                'max_results' => 20,
            ],
            'page_token' => 'allowed-provider-pagination-token',
        ];

        $this->assertSame([
            'query' => 'restaurants',
            'nested' => ['max_results' => 20],
            'page_token' => 'allowed-provider-pagination-token',
        ], AgentTaskInputSanitizer::sanitize($input));
    }
}
