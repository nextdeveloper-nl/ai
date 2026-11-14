<?php

namespace NextDeveloper\AI\Tests\Authorization;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\AI\Authorization\Roles\AIAdminRole;
use NextDeveloper\AI\Authorization\Roles\AIManagerRole;
use NextDeveloper\AI\Authorization\Roles\AIUserRole;
use NextDeveloper\IAM\Database\Models\Accounts;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;
use PHPUnit\Framework\TestCase;

/**
 * Proves the AI roles never let one account see or change the records of another account.
 * Runs against an in-memory sqlite database; the models are plain stand-ins for the generated ones.
 */
class AccountScopeTest extends TestCase
{
    private const ACCOUNT_A = 1;

    private const ACCOUNT_B = 2;

    private const USER_A1 = 11;

    private const USER_A2 = 12;

    protected function setUp(): void
    {
        parent::setUp();

        // No query is executed (the sandbox PHP has no sqlite); the connection only supplies the SQL grammar.
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $this->actAs(self::ACCOUNT_A, self::USER_A1);
    }

    protected function tearDown(): void
    {
        $this->setHelperState(null, null);
        parent::tearDown();
    }

    private function actAs(int $accountId, int $userId): void
    {
        $account = (new \ReflectionClass(Accounts::class))->newInstanceWithoutConstructor();
        $account->setRawAttributes(['id' => $accountId]);
        $user = (new \ReflectionClass(Users::class))->newInstanceWithoutConstructor();
        $user->setRawAttributes(['id' => $userId]);

        $this->setHelperState($user, $account);
    }

    private function setHelperState(?Users $user, ?Accounts $account): void
    {
        foreach (['user' => $user, 'account' => $account] as $property => $value) {
            $reflection = new \ReflectionProperty(UserHelper::class, $property);
            $reflection->setValue(null, $value);
        }
    }

    private function stand(string $table): Model
    {
        return new class($table) extends Model {
            public $timestamps = false;

            protected $guarded = [];

            public function __construct(string $table = '', array $attributes = [])
            {
                $this->table = $table;
                parent::__construct($attributes);
            }
        };
    }

    /**
     * The query the role builds for the table: SQL with its bindings inlined, so a test reads like the filter it checks.
     */
    private function scoped(object $role, string $table): string
    {
        $model = $this->stand($table);
        $query = $model->newQuery();
        $role->apply($query, $model);

        $sql = $query->toSql();

        foreach ($query->getBindings() as $binding) {
            $sql = preg_replace('/\\?/', is_bool($binding) ? (string) (int) $binding : (string) $binding, $sql, 1);
        }

        return $sql;
    }

    private function row(string $table, ?int $accountId, int $userId = 0): Model
    {
        $model = $this->stand($table);
        $model->setRawAttributes(['id' => 1, 'iam_account_id' => $accountId, 'iam_user_id' => $userId]);

        return $model;
    }

    public function test_manager_is_limited_to_their_own_account(): void
    {
        $this->assertStringContainsString('"ai_llm_api_keys"."iam_account_id" = 1', $this->scoped(new AIManagerRole(), 'ai_llm_api_keys'));

        $this->actAs(self::ACCOUNT_B, 21);
        $this->assertStringContainsString('"ai_llm_api_keys"."iam_account_id" = 2', $this->scoped(new AIManagerRole(), 'ai_llm_api_keys'));
    }

    public function test_manager_is_limited_on_every_account_table_not_just_keys(): void
    {
        foreach (['ai_llm_usage_logs', 'ai_llm_aliases', 'ai_agents', 'ai_runs', 'ai_llm_chats'] as $table) {
            $this->assertStringContainsString("\"{$table}\".\"iam_account_id\" = 1", $this->scoped(new AIManagerRole(), $table), $table);
        }
    }

    public function test_user_is_limited_to_their_own_account(): void
    {
        $this->assertStringContainsString('"ai_llm_api_keys"."iam_account_id" = 1', $this->scoped(new AIUserRole(), 'ai_llm_api_keys'));
    }

    public function test_admin_is_not_limited(): void
    {
        $this->assertStringNotContainsString('iam_account_id', $this->scoped(new AIAdminRole(), 'ai_llm_api_keys'));
    }

    public function test_manager_sees_the_whole_account_sessions_but_user_only_their_own(): void
    {
        $manager = $this->scoped(new AIManagerRole(), 'ai_sessions');
        $user = $this->scoped(new AIUserRole(), 'ai_sessions');

        $this->assertStringContainsString('"ai_sessions"."iam_account_id" = 1', $manager);
        $this->assertStringNotContainsString('iam_user_id', $manager);
        $this->assertStringContainsString('"ai_sessions"."iam_user_id" = '.self::USER_A1, $user);
    }

    public function test_conversations_follow_the_sessions_of_the_account(): void
    {
        $manager = $this->scoped(new AIManagerRole(), 'ai_conversations');
        $user = $this->scoped(new AIUserRole(), 'ai_conversations');

        $this->assertStringContainsString('from "ai_sessions" where "iam_account_id" = 1)', $manager);
        $this->assertStringNotContainsString('iam_user_id', $manager);
        $this->assertStringContainsString('"iam_user_id" = '.self::USER_A1, $user);
    }

    public function test_platform_providers_and_models_are_visible_but_other_accounts_are_not(): void
    {
        foreach ([new AIManagerRole(), new AIUserRole()] as $role) {
            foreach (['ai_llm_providers', 'ai_llm_models'] as $table) {
                $sql = $this->scoped($role, $table);
                $this->assertStringContainsString("\"{$table}\".\"iam_account_id\" is null or \"{$table}\".\"iam_account_id\" = 1", $sql);
            }
        }
    }

    public function test_helpers_are_public_or_own(): void
    {
        $this->assertStringContainsString(
            '"ai_available_helpers"."is_public" = 1 or "ai_available_helpers"."iam_account_id" = 1',
            $this->scoped(new AIManagerRole(), 'ai_available_helpers')
        );
    }

    public function test_manager_cannot_change_or_delete_another_accounts_record(): void
    {
        $manager = new AIManagerRole();
        $me = UserHelper::me();
        $foreignKey = $this->row('ai_llm_api_keys', self::ACCOUNT_B);

        $this->assertFalse($manager->checkUpdatePolicy($foreignKey, $me));
        $this->assertFalse($manager->checkDeletePolicy($foreignKey, $me));
    }

    public function test_manager_can_change_and_delete_a_record_of_their_own_account(): void
    {
        $manager = new AIManagerRole();
        $me = UserHelper::me();
        $ownKey = $this->row('ai_llm_api_keys', self::ACCOUNT_A);

        $this->assertTrue($manager->checkUpdatePolicy($ownKey, $me));
        $this->assertTrue($manager->checkDeletePolicy($ownKey, $me));
    }

    public function test_nobody_below_admin_can_change_a_platform_row(): void
    {
        $platformProvider = $this->row('ai_llm_providers', null);
        $me = UserHelper::me();

        foreach ([new AIManagerRole(), new AIUserRole()] as $role) {
            $this->assertFalse($role->checkUpdatePolicy($platformProvider, $me), get_class($role));
            $this->assertFalse($role->checkDeletePolicy($platformProvider, $me), get_class($role));
        }
    }

    public function test_user_cannot_change_a_colleagues_session_but_manager_can(): void
    {
        $colleagueSession = $this->row('ai_sessions', self::ACCOUNT_A, self::USER_A2);
        $me = UserHelper::me();

        $this->assertFalse((new AIUserRole())->checkUpdatePolicy($colleagueSession, $me));
        $this->assertTrue((new AIManagerRole())->checkUpdatePolicy($colleagueSession, $me));
    }
}
