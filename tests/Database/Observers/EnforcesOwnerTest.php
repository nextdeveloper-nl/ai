<?php

namespace NextDeveloper\AI\Tests\Database\Observers;

use Illuminate\Database\Eloquent\Model;
use NextDeveloper\AI\Database\Observers\EnforcesOwner;
use NextDeveloper\IAM\Database\Models\Accounts;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;
use PHPUnit\Framework\TestCase;

class EnforcesOwnerTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->actAs(null, null);
        parent::tearDown();
    }

    private function actAs(?int $accountId, ?int $userId): void
    {
        $account = $accountId ? (new \ReflectionClass(Accounts::class))->newInstanceWithoutConstructor() : null;
        $account?->setRawAttributes(['id' => $accountId]);
        $user = $userId ? (new \ReflectionClass(Users::class))->newInstanceWithoutConstructor() : null;
        $user?->setRawAttributes(['id' => $userId]);

        foreach (['user' => $user, 'account' => $account] as $property => $value) {
            (new \ReflectionProperty(UserHelper::class, $property))->setValue(null, $value);
        }
    }

    private function observer(bool $admin = false): object
    {
        return new class($admin)
        {
            use EnforcesOwner;

            public function __construct(private bool $admin) {}

            public function run(Model $model): void
            {
                $this->enforceOwner($model);
            }

            protected function isPlatformAdmin(): bool
            {
                return $this->admin;
            }
        };
    }

    private function record(array $attributes, bool $exists): Model
    {
        $model = new class extends Model
        {
            protected $guarded = [];
        };
        $model->setRawAttributes($attributes, true);
        $model->exists = $exists;

        return $model;
    }

    public function test_a_new_record_gets_the_current_account_and_user_whatever_was_sent(): void
    {
        $this->actAs(1, 11);
        $model = $this->record(['iam_account_id' => 2, 'iam_user_id' => 99], false);

        $this->observer()->run($model);

        $this->assertSame(1, $model->iam_account_id);
        $this->assertSame(11, $model->iam_user_id);
    }

    public function test_an_existing_record_cannot_be_handed_to_another_account(): void
    {
        $this->actAs(1, 11);
        $model = $this->record(['iam_account_id' => 1, 'iam_user_id' => 11], true);
        $model->iam_account_id = 2;
        $model->iam_user_id = 99;

        $this->observer()->run($model);

        $this->assertSame(1, $model->iam_account_id);
        $this->assertSame(11, $model->iam_user_id);
    }

    public function test_ai_admin_may_write_for_other_accounts(): void
    {
        $this->actAs(1, 11);
        $model = $this->record(['iam_account_id' => 2, 'iam_user_id' => 99], false);

        $this->observer(admin: true)->run($model);

        $this->assertSame(2, $model->iam_account_id);
        $this->assertSame(99, $model->iam_user_id);
    }
}
