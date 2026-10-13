<?php

declare(strict_types=1);

namespace WsForms\Api;

use RuntimeException;
use VbLaravel\Attributes\Api;
use VbLaravel\Attributes\Can;
use WsForms\Models\Form;
use WsForms\Models\FormSubmission;
use WsForms\Models\FormSubmissionValue;

class FormController
{
    #[Api('wsforms:form', 'get')]
    #[Can('canview', 'genericpermissions')]
    public function get(int $formId): array
    {
        $form = Form::with('fields')->where('enabled', true)->find($formId);
        if (!$form) {
            return ['error' => 'form_not_found'];
        }

        return [
            'id' => $form->id,
            'title' => $form->title,
            'fields' => $form->fields->toArray(),
        ];
    }

    #[Api('wsforms:form', 'submit')]
    #[Can('canview', 'genericpermissions')]
    public function submit(int $formId, array $values, ?string $securitytoken = null): array
    {
        // CSRF Verification for write endpoint
        if (!class_exists('vB', false) || empty($securitytoken)) {
            // Check session securitytoken if in vB environment
            $user = vb()->user();
            if (!empty($user['securitytoken']) && $securitytoken !== $user['securitytoken']) {
                throw new RuntimeException('CSRF security token mismatch.');
            }
        }

        $form = Form::where('enabled', true)->findOrFail($formId);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'userid' => vb()->userId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'created_at' => time(),
        ]);

        foreach ($values as $fieldId => $val) {
            FormSubmissionValue::create([
                'submission_id' => $submission->id,
                'field_id' => (int) $fieldId,
                'value' => (string) $val,
            ]);
        }

        return [
            'success' => true,
            'submission_id' => $submission->id,
        ];
    }
}
