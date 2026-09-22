<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Kommandhub\DemoData\Util\DemoDataConstants;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * Creates the administrator account that goes out to prospective customers.
 *
 * The account is an ordinary Shopware user with `admin` false and exactly one
 * ACL role, which carries every read privilege in the installation and no
 * create, update or delete. `admin` false is the load-bearing part: an
 * administrator flagged as admin bypasses ACL entirely, so the role would be
 * decoration and the visitor could edit the shop.
 *
 * Idempotent like the rest of the plugin. The role's privileges are rewritten
 * on every run — they are ours, and a Shopware update that adds an entity
 * should reach the demo account — while the account's password is only ever
 * written when this run chose it.
 */
class DemoUserProvisioner
{
    public const DEFAULT_EMAIL = 'demo@example.com';
    public const DEFAULT_USERNAME = 'demo';
    public const ROLE_NAME = 'Demo (read only)';

    private const KEY = 'demo-viewer';

    /**
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     * @param EntityRepository<LocaleCollection> $localeRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $aclRoleRepository,
        private readonly EntityRepository $localeRepository,
        private readonly ReadOnlyPrivileges $privileges,
        private readonly DemoIdGenerator $ids
    ) {
    }

    public function provision(
        Context $context,
        ?string $email = null,
        ?string $username = null,
        ?string $password = null,
        bool $rotatePassword = false
    ): DemoUserAccount {
        $email = $email ?? self::DEFAULT_EMAIL;
        $username = $username ?? self::DEFAULT_USERNAME;

        $userId = $this->ids->id('user', self::KEY);
        $matches = $this->matching($context, $userId, $email, $username);

        // Any row but our own that answers to this email or username belongs to
        // somebody else. Taking the login from them is not ours to do — and
        // checking only the first match would miss it whenever our own account
        // happens to be the row that comes back.
        foreach ($matches as $match) {
            if ($match->getId() !== $userId) {
                return DemoUserAccount::refused($match->getId(), $email, $username);
            }
        }

        $existing = $matches[$userId] ?? null;

        $privileges = $this->privileges->all();
        $roleId = $this->writeRole($context, $privileges);

        $payload = [
            'id' => $userId,
            'localeId' => $this->localeId($context),
            'username' => $username,
            'email' => $email,
            'firstName' => 'Demo',
            'lastName' => 'Viewer',
            'timeZone' => 'UTC',
            'active' => true,
            // Not a formality. An admin user is exempt from ACL, so leaving this
            // true would give the visitor the whole shop despite the role.
            'admin' => false,
            'aclRoles' => [['id' => $roleId]],
            'customFields' => [
                DemoDataConstants::FIELD_SOURCE_KEY => $this->ids->key('user', self::KEY),
                DemoDataConstants::FIELD_GENERATED => true,
            ],
        ];

        if ($existing === null) {
            $password = $password ?? self::generatePassword();
            $payload['password'] = $password;

            $this->write($context, fn (Context $scoped) => $this->userRepository->create([$payload], $scoped));

            return DemoUserAccount::created($userId, $email, $username, $password, \count($privileges));
        }

        if ($password !== null || $rotatePassword) {
            $password = $password ?? self::generatePassword();
            $payload['password'] = $password;
        } else {
            $password = null;
        }

        $this->write($context, fn (Context $scoped) => $this->userRepository->update([$payload], $scoped));

        return DemoUserAccount::updated($userId, $email, $username, $password, \count($privileges));
    }

    /**
     * What the command and the administration module report without writing.
     *
     * @return array{exists: bool, email: ?string, username: ?string, active: ?bool, privileges: int}
     */
    public function describe(Context $context): array
    {
        $criteria = new Criteria([$this->ids->id('user', self::KEY)]);
        $criteria->addAssociation('aclRoles');

        $user = $this->userRepository->search($criteria, $context)->getEntities()->first();

        if ($user === null) {
            return ['exists' => false, 'email' => null, 'username' => null, 'active' => null, 'privileges' => 0];
        }

        $role = $user->getAclRoles()?->first();

        return [
            'exists' => true,
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'active' => $user->getActive(),
            'privileges' => \count($role?->getPrivileges() ?? []),
        ];
    }

    /**
     * @param array<int, string> $privileges
     */
    private function writeRole(Context $context, array $privileges): string
    {
        $roleId = $this->ids->id('acl_role', self::KEY);

        $payload = [
            'id' => $roleId,
            'name' => self::ROLE_NAME,
            'description' => 'Every read privilege in this installation and nothing that writes. Created by the Kommandhub demo data plugin.',
            'privileges' => $privileges,
        ];

        $this->write($context, fn (Context $scoped) => $this->aclRoleRepository->upsert([$payload], $scoped));

        return $roleId;
    }

    /**
     * Every account that answers to this id, email or username.
     *
     * Deliberately unbounded rather than `setLimit(1)`: the question is not
     * "does an account exist" but "does one exist that is not ours", and a
     * limit answers the first of those while looking like it answered the
     * second.
     *
     * @return array<string, UserEntity> keyed by id
     */
    private function matching(Context $context, string $userId, string $email, string $username): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('id', $userId),
            new EqualsFilter('email', $email),
            new EqualsFilter('username', $username),
        ]));

        // Hoisted out of the foreach: the search is one query either way, but
        // the static analyser's N+1 rule reads a repository call in the loop
        // expression as a call per iteration.
        $found = $this->userRepository->search($criteria, $context)->getEntities();

        $users = [];

        foreach ($found as $user) {
            $users[$user->getId()] = $user;
        }

        return $users;
    }

    /**
     * The locale the shop's own administrators use, so the demo account opens
     * in the language the rest of the installation is administered in.
     */
    private function localeId(Context $context): string
    {
        $anyUser = new Criteria();
        $anyUser->setLimit(1);

        $user = $this->userRepository->search($anyUser, $context)->getEntities()->first();

        if ($user !== null) {
            return $user->getLocaleId();
        }

        $locale = new Criteria();
        $locale->addFilter(new EqualsFilter('code', 'en-GB'));
        $locale->setLimit(1);

        $localeId = $this->localeRepository->searchIds($locale, $context)->firstId();

        if ($localeId !== null) {
            return $localeId;
        }

        $any = new Criteria();
        $any->setLimit(1);

        $fallback = $this->localeRepository->searchIds($any, $context)->firstId();

        if ($fallback === null) {
            throw new \RuntimeException('The installation has no locales, so no user can be created.');
        }

        return $fallback;
    }

    /**
     * `user` and `acl_role` both carry a write protection for the system scope,
     * so an ordinary context is refused with "insufficient privileges".
     *
     * @param \Closure(Context): mixed $write
     */
    private function write(Context $context, \Closure $write): void
    {
        $context->scope(Context::SYSTEM_SCOPE, $write);
    }

    /**
     * 22 characters of base64url from the CSPRNG. Long enough that publishing
     * the account on a slide does not matter, short enough to be typed.
     */
    private static function generatePassword(): string
    {
        return substr(rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '='), 0, 22);
    }
}
