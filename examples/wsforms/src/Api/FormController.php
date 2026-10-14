<?php

declare(strict_types=1);

namespace WsForms\Api;

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
            throw new \vB_Exception_Api('form_not_found');
        }

        return [
            'id' => $form->id,
            'title' => $form->title,
            'fields' => $form->fields->toArray(),
        ];
    }

    #[Api(
        controller: 'wsforms:form',
        action: 'submit',
        write: true
    )]
    #[Can('canview', 'genericpermissions')]
    public function submit(int $formId, array $values): array
    {
        $form = Form::where('enabled', true)->find($formId);
        if (!$form) {
            throw new \vB_Exception_Api('form_not_found');
        }

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
