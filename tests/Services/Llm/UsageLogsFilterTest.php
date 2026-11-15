<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;
use NextDeveloper\AI\Database\Filters\LlmUsageLogsQueryFilter;
use PHPUnit\Framework\TestCase;

class UsageLogsFilterTest extends TestCase
{
    public function test_none_selects_the_calls_made_without_an_api_key(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();

        foreach (['none', 'NULL'] as $value) {
            $filter = new LlmUsageLogsQueryFilter(new Request());
            $builder = Capsule::table('ai_llm_usage_logs');

            $reflection = new \ReflectionProperty($filter, 'builder');
            $reflection->setAccessible(true);
            $reflection->setValue($filter, $builder);

            $filter->aiLlmApiKeyId($value);

            $this->assertStringContainsString('"ai_llm_api_key_id" is null', $builder->toSql());
        }
    }
}
