<?php

declare(strict_types=1);

class vblaraveltest_Api_Note extends vB_Api
{
    public function fetch(): array
    {
        \VbLaravel\AddonLoader::load(DIR . '/packages/vblaraveltest');

        $user = vb()->user();
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message !== '') {
            \VB\LaravelTest\Note::create([
                'userid' => (int) ($user['userid'] ?? 0),
                'message' => substr($message, 0, 255),
            ]);
        }

        return [
            'userid' => (int) ($user['userid'] ?? 0),
            'notes' => \VB\LaravelTest\Note::query()->latest('id')->limit(20)->get()->toArray(),
        ];
    }
}
