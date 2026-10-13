<?php

declare(strict_types=1);

namespace VbLaravel;

final class Bridge
{
    private ?array $currentUser = null;

    public function user(): array
    {
        if ($this->currentUser !== null) {
            return $this->currentUser;
        }

        $info = $this->call('user', 'fetchCurrentUserinfo');
        if (!is_array($info) || isset($info['errors'])) {
            return $this->currentUser = [];
        }

        return $this->currentUser = $info;
    }

    public function userId(): int
    {
        return (int) ($this->user()['userid'] ?? 0);
    }

    public function isLoggedIn(): bool
    {
        return $this->userId() > 0;
    }

    public function inGroup(int|array $groupIds, ?int $userId = null): bool
    {
        $user = ($userId === null || $userId === $this->userId())
            ? $this->user()
            : (array) $this->call('user', 'fetchUserinfo', [$userId]);

        if (empty($user) || !isset($user['usergroupid'])) {
            return false;
        }

        $targetGroups = (array) $groupIds;
        $userGroups = [(int) $user['usergroupid']];

        if (!empty($user['membergroupids'])) {
            $members = is_string($user['membergroupids'])
                ? explode(',', $user['membergroupids'])
                : (array) $user['membergroupids'];
            foreach ($members as $gid) {
                $userGroups[] = (int) $gid;
            }
        }

        return !empty(array_intersect($targetGroups, $userGroups));
    }

    public function setting(string $name, mixed $default = null): mixed
    {
        if (class_exists('vB', false) && method_exists('vB', 'getDatastore')) {
            $val = \vB::getDatastore()->getOption($name);
            return $val !== null ? $val : $default;
        }

        return $default;
    }

    public function session(): mixed
    {
        if (class_exists('vB', false) && method_exists('vB', 'getCurrentSession')) {
            return \vB::getCurrentSession();
        }

        return null;
    }

    public function request(): mixed
    {
        return $this->call('request', 'getRequestInfo');
    }

    public function phrase(string $name): mixed
    {
        return $this->call('phrase', 'fetch', [$name]);
    }

    public function node(int $id): mixed
    {
        return $this->call('node', 'getNode', [$id]);
    }

    public function can(string $permission, string $group = 'genericpermissions'): bool
    {
        if (!class_exists('vB', false) || !method_exists('vB', 'getUserContext')) {
            return false;
        }

        try {
            $context = \vB::getUserContext();
            if (!$context || !method_exists($context, 'hasPermission')) {
                return false;
            }

            return (bool) $context->hasPermission($group, $permission);
        } catch (\Throwable) {
            return false;
        }
    }

    public function url(string $route, array $data = [], array $extra = []): string
    {
        if (class_exists('vB5_Route', false) && method_exists('vB5_Route', 'buildUrl')) {
            return \vB5_Route::buildUrl($route, $data, $extra);
        }

        return $route;
    }

    public function api(string $controller, ?string $method = null, array $arguments = []): mixed
    {
        if ($method === null) {
            return class_exists('vB_Api', false) ? \vB_Api::instance($controller) : null;
        }

        return $this->call($controller, $method, $arguments);
    }

    public function resetUser(): void
    {
        $this->currentUser = null;
    }

    private function call(string $controller, string $method, array $arguments = []): mixed
    {
        if (class_exists('Api_InterfaceAbstract', false) && method_exists('Api_InterfaceAbstract', 'instance')) {
            return \Api_InterfaceAbstract::instance()->callApi($controller, $method, $arguments);
        }

        return null;
    }
}
