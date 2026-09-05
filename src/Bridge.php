<?php

declare(strict_types=1);

namespace VbLaravel;

final class Bridge
{
    public function user(): mixed
    {
        return $this->call('user', 'fetchCurrentUserinfo');
    }

    public function userId(): int
    {
        $user = (array) $this->user();
        return (int) ($user['userid'] ?? 0);
    }

    public function isLoggedIn(): bool
    {
        return $this->userId() > 0;
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
        return (bool) \vB::getUserContext()->hasPermission($group, $permission);
    }

    public function url(string $route, array $data = [], array $extra = []): string
    {
        return \vB5_Route::buildUrl($route, $data, $extra);
    }

    public function api(string $controller, ?string $method = null, array $arguments = []): mixed
    {
        if ($method === null) {
            return \vB_Api::instance($controller);
        }

        return $this->call($controller, $method, $arguments);
    }

    private function call(string $controller, string $method, array $arguments = []): mixed
    {
        return \Api_InterfaceAbstract::instance()->callApi($controller, $method, $arguments);
    }
}
