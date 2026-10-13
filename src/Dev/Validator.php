<?php

declare(strict_types=1);

namespace VbLaravel\Dev;

final class Validator
{
    private const KNOWN_VB6_PHP_HOOKS = [
        'hookFrontendContentBeforeAdd',
        'hookFrontendContentAfterAdd',
        'hookFrontendContentBeforeUpdate',
        'hookFrontendContentAfterUpdate',
        'hookUserAfterSave',
        'hookRegistrationBeforeSave',
        'hookUserModerationApproved',
        'hookUserAfterActivation',
        'hookGetRoutingControllerActionWhitelist',
        'hookCanVoteNode',
        'hookCanUnvoteNode',
        'hookNodevoteAggregateChange',
        'hookExternalLoginPostUserDelete',
        'hookAdminCPUserExternalConnections',
    ];

    public static function validate(string $addonPath): array
    {
        $addonPath = realpath($addonPath) ?: rtrim($addonPath, '/\\');
        $errors = [];

        if (!is_dir($addonPath)) {
            return ["Directory not found: {$addonPath}"];
        }

        $manifestFile = $addonPath . '/vb-addon.json';
        if (!is_file($manifestFile)) {
            return ["Missing manifest: {$manifestFile}"];
        }

        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        if (!is_array($manifest)) {
            return ["Invalid JSON in manifest: {$manifestFile}"];
        }

        // Validate ID pattern: ^[a-z][a-z0-9]{2,24}$
        if (empty($manifest['id']) || !preg_match('/^[a-z][a-z0-9]{2,24}$/', (string) $manifest['id'])) {
            $errors[] = "Manifest 'id' must match pattern ^[a-z][a-z0-9]{2,24}$ (got '" . ($manifest['id'] ?? '') . "').";
        }

        // Validate Title or Name
        if (empty($manifest['title']) && empty($manifest['name'])) {
            $errors[] = "Manifest requires 'title' or 'name'.";
        }

        // Validate Version
        if (empty($manifest['version']) || !preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/', (string) $manifest['version'])) {
            $errors[] = "Manifest 'version' must follow semver (e.g. 1.0.0).";
        }

        // Validate Runtime: ^\^(\d+)\.(\d+)(?:\.(\d+))?$
        if (empty($manifest['runtime']) || !preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', (string) $manifest['runtime'])) {
            $errors[] = "Manifest 'runtime' must be a caret semver constraint (e.g. ^0.2, ^0.10, ^1.0).";
        }

        // Validate Provider
        if (empty($manifest['provider'])) {
            $errors[] = "Manifest requires a 'provider' class name.";
        }

        // Scan PHP files in src/
        $srcDir = $addonPath . '/src';
        if (is_dir($srcDir)) {
            $phpFiles = self::rglob($srcDir, '*.php');
            foreach ($phpFiles as $file) {
                $code = (string) file_get_contents($file);

                // AST / Token inspection
                $tokens = token_get_all($code);
                $hasCan = false;
                $hasApi = false;
                $hasHook = false;

                // Inspect attributes syntax #[...]
                if (preg_match_all('/#\[([^\]]+)\]/', $code, $attrMatches)) {
                    foreach ($attrMatches[1] as $attrStr) {
                        if (str_contains($attrStr, 'Can(') || $attrStr === 'Can') {
                            $hasCan = true;
                        }
                        if (str_contains($attrStr, 'Api(') || $attrStr === 'Api') {
                            $hasApi = true;
                        }
                        if (preg_match('/Hook\s*\(\s*[\'"]([^\'"]+)[\'"]/', $attrStr, $hookMatches)) {
                            $hasHook = true;
                            $hookName = $hookMatches[1];

                            // Flag old vB3/vB4 hooks
                            if (in_array($hookName, ['init_startup', 'thread_post_complete', 'global_bootstrap'], true)) {
                                $errors[] = "Deprecated/invalid hook '{$hookName}' in {$file}. Use real vB6 hook names.";
                            }

                            // Warn if template hook in #[Hook]
                            if (in_array($hookName, ['header_head', 'header_topusermenu_external_login'], true)) {
                                $errors[] = "Template hook '{$hookName}' found in #[Hook] attribute in {$file}. Template hooks must be placed under templates/hooks/<hookname>.html, not #[Hook].";
                            }
                        }
                    }
                }

                // #[Can] is only allowed on #[Api]
                if ($hasCan && !$hasApi) {
                    $errors[] = "#[Can] attribute found in {$file} without #[Api]. #[Can] is only allowed on #[Api] endpoints.";
                }
            }
        }

        // Validate migrations naming: YYYY_MM_DD_HHMMSS_*.php
        $migrationsDir = $addonPath . '/database/migrations';
        if (is_dir($migrationsDir)) {
            $migrationFiles = glob($migrationsDir . '/*.php') ?: [];
            foreach ($migrationFiles as $mf) {
                $base = basename($mf, '.php');
                if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+$/', $base)) {
                    $errors[] = "Migration filename '{$base}' in {$mf} must follow format YYYY_MM_DD_HHMMSS_name.php.";
                }
            }
        }

        return $errors;
    }

    private static function rglob(string $dir, string $pattern): array
    {
        $files = glob($dir . '/' . $pattern) ?: [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
            $files = array_merge($files, self::rglob($sub, $pattern));
        }
        return $files;
    }
}
