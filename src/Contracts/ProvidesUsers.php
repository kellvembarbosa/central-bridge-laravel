<?php

namespace Applu\CentralBridge\Contracts;

use Applu\CentralBridge\Data\Page;
use Applu\CentralBridge\Data\UserDetail;

/** Capabilities `users_search` and `user_detail`. */
interface ProvidesUsers
{
    /**
     * @return Page<\Applu\CentralBridge\Data\UserSummary>
     */
    public function searchUsers(?string $query, int $page, int $perPage): Page;

    /**
     * Return null (or throw UserNotFound) when the user does not exist.
     */
    public function findUser(string $ref): ?UserDetail;
}
