<?php

namespace NextDeveloper\AI\Tests\Database\Models;

use Illuminate\Database\Capsule\Manager as Capsule;
use NextDeveloper\AI\Database\Models\LlmChats;
use PHPUnit\Framework\TestCase;

class LlmChatsScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only the SQL is inspected, nothing runs (the dev PHP has no sqlite driver).
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    private function sql(array $owner): string
    {
        $query = LlmChats::ownedBy($owner);
        $sql = $query->toSql();

        foreach ($query->getBindings() as $binding) {
            $sql = preg_replace('/\?/', (string) $binding, $sql, 1);
        }

        return $sql;
    }

    public function test_a_chat_is_limited_to_its_user_and_account(): void
    {
        $sql = $this->sql(['user' => 11, 'account' => 1]);

        $this->assertStringContainsString('"ai_llm_chats"."iam_user_id" = 11', $sql);
        $this->assertStringContainsString('"ai_llm_chats"."iam_account_id" = 1', $sql);
    }

    public function test_the_same_user_in_another_account_gets_another_filter(): void
    {
        $this->assertStringContainsString('"ai_llm_chats"."iam_account_id" = 2', $this->sql(['user' => 11, 'account' => 2]));
    }
}
