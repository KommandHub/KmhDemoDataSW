<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

/**
 * What happened to the demo administrator, and what to tell the operator.
 *
 * `$password` is set only when this run chose one — on creation, or when a
 * rotation was asked for. Every other run returns null, because the stored
 * value is a hash and the plaintext is gone. Nothing here is logged.
 */
final class DemoUserAccount
{
    /**
     * @param int $privileges number of privileges on the role, for reporting
     */
    private function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $username,
        public readonly ?string $password,
        public readonly bool $created,
        public readonly bool $refused,
        public readonly int $privileges
    ) {
    }

    public static function created(string $id, string $email, string $username, string $password, int $privileges): self
    {
        return new self($id, $email, $username, $password, true, false, $privileges);
    }

    public static function updated(string $id, string $email, string $username, ?string $password, int $privileges): self
    {
        return new self($id, $email, $username, $password, false, false, $privileges);
    }

    /**
     * An account with this email or username exists and this plugin did not
     * create it. Touching it would hand a stranger's login to whoever the demo
     * goes to — or lock out the shop's own administrator by resetting their
     * password — so the run stops instead.
     */
    public static function refused(string $id, string $email, string $username): self
    {
        return new self($id, $email, $username, null, false, true, 0);
    }
}
