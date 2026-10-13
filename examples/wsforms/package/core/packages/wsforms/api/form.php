<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/product.php';

class wsforms_Api_Form extends \vB_Api
{
    public function get(int $formId = 0): array
    {
        \VbLaravel\Runtime::boot();

        if (!vb()->can('canview', 'genericpermissions')) {
            throw new \vB_Exception_Api('no_permission');
        }

        $controller = new \WsForms\Api\FormController();
        return $controller->get($formId);
    }

    public function submit(int $formId = 0, array $values = [], ?string $securitytoken = null): array
    {
        \VbLaravel\Runtime::boot();

        if (!vb()->can('canview', 'genericpermissions')) {
            throw new \vB_Exception_Api('no_permission');
        }

        $user = vb()->user();
        $token = $securitytoken ?? ($_POST['securitytoken'] ?? ($_REQUEST['securitytoken'] ?? null));
        if (empty($token) || !isset($user['securitytoken']) || $token !== $user['securitytoken']) {
            throw new \vB_Exception_Api('security_token_invalid');
        }

        $controller = new \WsForms\Api\FormController();
        return $controller->submit($formId, $values, $securitytoken);
    }

}
