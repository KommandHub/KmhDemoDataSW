<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Service\DemoIdGenerator;
use Kommandhub\DemoData\Service\DemoUserProvisioner;
use Kommandhub\DemoData\Service\ReadOnlyPrivileges;
use Kommandhub\DemoData\Util\DemoDataConstants;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

class DemoUserProvisionerTest extends TestCase
{
    private EntityRepository&MockObject $users;
    private EntityRepository&MockObject $roles;
    private EntityRepository&MockObject $locales;
    private DemoIdGenerator $ids;

    protected function setUp(): void
    {
        $this->users = $this->createMock(EntityRepository::class);
        $this->roles = $this->createMock(EntityRepository::class);
        $this->locales = $this->createMock(EntityRepository::class);
        $localeIds = $this->createMock(IdSearchResult::class);
        $localeIds->method('firstId')->willReturn('locale-id');
        $this->locales->method('searchIds')->willReturn($localeIds);
        $this->ids = new DemoIdGenerator();
    }

    public function testCreatesTheAccountWithAPasswordAndNoAdminFlag(): void
    {
        $this->usersFound([]);

        $written = null;
        $this->users->expects($this->once())->method('create')->willReturnCallback(
            function (array $payload) use (&$written): EntityWrittenContainerEvent {
                $written = $payload[0];

                return $this->writeEvent();
            }
        );

        $account = $this->provisioner()->provision(Context::createDefaultContext());

        $this->assertTrue($account->created);
        $this->assertNotNull($account->password);
        $this->assertSame($account->password, $written['password']);
        // An admin user bypasses ACL entirely, which would make the role decoration.
        $this->assertFalse($written['admin']);
        $this->assertTrue($written['active']);
        $this->assertSame(DemoUserProvisioner::DEFAULT_EMAIL, $written['email']);
        $this->assertTrue($written['customFields'][DemoDataConstants::FIELD_GENERATED]);
    }

    public function testTheGeneratedPasswordIsLongAndDiffersEveryTime(): void
    {
        $this->usersFound([]);
        $this->users->method('create');

        $first = $this->provisioner()->provision(Context::createDefaultContext())->password;
        $second = $this->provisioner()->provision(Context::createDefaultContext())->password;

        $this->assertNotNull($first);
        $this->assertGreaterThanOrEqual(20, \strlen($first));
        $this->assertNotSame($first, $second);
    }

    public function testTheRoleCarriesTheReadOnlyPrivileges(): void
    {
        $this->usersFound([]);
        $this->users->method('create');

        $role = null;
        $this->roles->expects($this->once())->method('upsert')->willReturnCallback(
            function (array $payload) use (&$role): EntityWrittenContainerEvent {
                $role = $payload[0];

                return $this->writeEvent();
            }
        );

        $this->provisioner()->provision(Context::createDefaultContext());

        $this->assertContains('product:read', $role['privileges']);
        $this->assertContains('product.viewer', $role['privileges']);
        $this->assertSame($this->ids->id('acl_role', 'demo-viewer'), $role['id']);
    }

    /**
     * A second run must not reset the password of an account somebody is using.
     */
    public function testAnExistingAccountKeepsItsPasswordUnlessARotationIsAsked(): void
    {
        $this->usersFound([$this->user($this->ids->id('user', 'demo-viewer'))]);

        $written = null;
        $this->users->expects($this->once())->method('update')->willReturnCallback(
            function (array $payload) use (&$written): EntityWrittenContainerEvent {
                $written = $payload[0];

                return $this->writeEvent();
            }
        );

        $account = $this->provisioner()->provision(Context::createDefaultContext());

        $this->assertFalse($account->created);
        $this->assertNull($account->password);
        $this->assertArrayNotHasKey('password', $written);
    }

    public function testRotationChoosesANewPassword(): void
    {
        $this->usersFound([$this->user($this->ids->id('user', 'demo-viewer'))]);

        $written = null;
        $this->users->method('update')->willReturnCallback(
            function (array $payload) use (&$written): EntityWrittenContainerEvent {
                $written = $payload[0];

                return $this->writeEvent();
            }
        );

        $account = $this->provisioner()->provision(Context::createDefaultContext(), null, null, null, true);

        $this->assertNotNull($account->password);
        $this->assertSame($account->password, $written['password']);
    }

    /**
     * Writing to a login this plugin did not mint would either hand out a real
     * administrator's account or reset their password out from under them.
     */
    public function testRefusesToTouchAnAccountItDidNotCreate(): void
    {
        $this->usersFound([$this->user('someone-elses-id')]);

        $this->users->expects($this->never())->method('create');
        $this->users->expects($this->never())->method('update');
        $this->roles->expects($this->never())->method('upsert');

        $account = $this->provisioner()->provision(Context::createDefaultContext());

        $this->assertTrue($account->refused);
        $this->assertNull($account->password);
    }

    /**
     * The regression that motivated searching without a limit: with our own
     * account present, a request naming somebody else's email matched our row
     * first, passed the "is this ours?" check, and renamed our account to
     * theirs.
     */
    public function testRefusesAForeignLoginEvenWhenOurOwnAccountAlsoMatches(): void
    {
        $ours = $this->user($this->ids->id('user', 'demo-viewer'));
        $theirs = $this->user('a-real-administrator');
        $theirs->setEmail('admin@example.com');
        $theirs->setUsername('admin');

        $this->usersFound([$ours, $theirs]);

        $this->users->expects($this->never())->method('update');
        $this->users->expects($this->never())->method('create');

        $account = $this->provisioner()->provision(
            Context::createDefaultContext(),
            'admin@example.com',
            'admin'
        );

        $this->assertTrue($account->refused);
    }

    public function testDescribeReportsNothingWhenTheAccountIsAbsent(): void
    {
        $this->usersFound([]);

        $this->assertSame(
            ['exists' => false, 'email' => null, 'username' => null, 'active' => null, 'privileges' => 0],
            $this->provisioner()->describe(Context::createDefaultContext())
        );
    }

    /**
     * @param array<int, UserEntity> $entities
     */
    private function usersFound(array $entities): void
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new UserCollection($entities));
        $this->users->method('search')->willReturn($result);
    }

    private function writeEvent(): EntityWrittenContainerEvent
    {
        return EntityWrittenContainerEvent::createWithWrittenEvents([], Context::createDefaultContext(), []);
    }

    private function user(string $id): UserEntity
    {
        $user = new UserEntity();
        $user->setId($id);
        $user->setEmail(DemoUserProvisioner::DEFAULT_EMAIL);
        $user->setUsername(DemoUserProvisioner::DEFAULT_USERNAME);
        $user->setLocaleId('locale-id');

        return $user;
    }

    private function provisioner(): DemoUserProvisioner
    {
        $definition = $this->createMock(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn(['product' => $definition]);

        /** @var EntityRepository<UserCollection> $users */
        $users = $this->users;
        /** @var EntityRepository<AclRoleCollection> $roles */
        $roles = $this->roles;
        /** @var EntityRepository<LocaleCollection> $locales */
        $locales = $this->locales;

        return new DemoUserProvisioner($users, $roles, $locales, new ReadOnlyPrivileges($registry), $this->ids);
    }
}
