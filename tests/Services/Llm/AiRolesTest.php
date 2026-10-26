<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use NextDeveloper\AI\Services\AiRolesService;
use PHPUnit\Framework\TestCase;

class AiRolesTest extends TestCase
{
    public function test_everybody_gets_the_default_roles(): void
    {
        $this->assertSame(['ai-user'], AiRolesService::wanted(['ai-user'], ['ai-manager'], false));
    }

    public function test_the_owner_gets_the_owner_roles_on_top(): void
    {
        $this->assertSame(['ai-user', 'ai-manager'], AiRolesService::wanted(['ai-user'], ['ai-manager'], true));
    }

    public function test_a_role_listed_twice_or_not_a_name_is_ignored(): void
    {
        $this->assertSame(['ai-user'], AiRolesService::wanted(['ai-user', 'ai-user', null, 7], ['ai-user'], true));
        $this->assertSame([], AiRolesService::wanted([], [], true));
    }
}
