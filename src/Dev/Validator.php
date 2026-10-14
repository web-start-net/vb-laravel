<?php

declare(strict_types=1);

namespace VbLaravel\Dev;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

final class Validator
{
    private static ?array $vb6HooksData = null;

    /**
     * Load the canonical vBulletin 6 hooks inventory (data/vb6-hooks.json).
     */
    public static function getHooksData(): array
    {
        if (self::$vb6HooksData !== null) {
            return self::$vb6HooksData;
        }

        $file = dirname(__DIR__, 2) . '/data/vb6-hooks.json';
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                self::$vb6HooksData = $data;
                return self::$vb6HooksData;
            }
        }

        self::$vb6HooksData = ['php_hooks' => [], 'template_hooks' => []];
        return self::$vb6HooksData;
    }

    /**
     * Suggest the closest matching identifier from a list using Levenshtein distance.
     */
    public static function suggestClosest(string $needle, array $haystack): ?string
    {
        $best = null;
        $bestDist = PHP_INT_MAX;
        $needleLower = strtolower($needle);

        foreach ($haystack as $candidate) {
            $dist = levenshtein($needleLower, strtolower($candidate));
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $candidate;
            }
        }

        if ($best !== null && ($bestDist <= 5 || $bestDist <= (int) (strlen($needle) * 0.4))) {
            return $best;
        }

        return null;
    }

    public static function validate(string $addonPath): array
    {
        if (!class_exists(ParserFactory::class)) {
            $devAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (is_file($devAutoload)) {
                require_once $devAutoload;
            }
        }

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

        // Validate Runtime: ^\^(\d+)\.(\d+)(?:\.(\d+))?$; allow ^0.2, ^0.10, ^1.0
        if (empty($manifest['runtime']) || !preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', (string) $manifest['runtime'])) {
            $errors[] = "Manifest 'runtime' must be a caret semver constraint (e.g. ^0.2, ^0.10, ^1.0).";
        }

        // Validate Provider
        if (empty($manifest['provider'])) {
            $errors[] = "Manifest requires a 'provider' class name.";
        }

        $hooksData = self::getHooksData();
        $validPhpHooks = array_keys($hooksData['php_hooks'] ?? []);
        $validTemplateHooks = array_keys($hooksData['template_hooks'] ?? []);

        // Scan PHP files in src/ using AST
        $srcDir = $addonPath . '/src';
        if (is_dir($srcDir) && class_exists(ParserFactory::class)) {
            $parser = (new ParserFactory())->createForHostVersion();
            $phpFiles = self::rglob($srcDir, '*.php');

            foreach ($phpFiles as $file) {
                if (str_contains(str_replace('\\', '/', $file), '/_Generated/')) {
                    continue;
                }

                try {
                    $stmts = $parser->parse((string) file_get_contents($file)) ?? [];
                } catch (\Throwable $e) {
                    $errors[] = "Parse error in {$file}: " . $e->getMessage();
                    continue;
                }

                $imports = [];
                $classes = [];

                foreach ($stmts as $stmt) {
                    if ($stmt instanceof Stmt\Namespace_) {
                        foreach ($stmt->stmts as $inner) {
                            if ($inner instanceof Stmt\Use_) {
                                foreach ($inner->uses as $u) {
                                    $alias = $u->alias ? $u->alias->name : $u->name->getLast();
                                    $imports[$alias] = $u->name->toString();
                                }
                            } elseif ($inner instanceof Stmt\Class_) {
                                $classes[] = $inner;
                            }
                        }
                    } elseif ($stmt instanceof Stmt\Use_) {
                        foreach ($stmt->uses as $u) {
                            $alias = $u->alias ? $u->alias->name : $u->name->getLast();
                            $imports[$alias] = $u->name->toString();
                        }
                    } elseif ($stmt instanceof Stmt\Class_) {
                        $classes[] = $stmt;
                    }
                }

                foreach ($classes as $cls) {
                    foreach ($cls->getMethods() as $method) {
                        $hasApi = false;
                        $hasCan = false;
                        $hasHook = false;

                        foreach ($method->attrGroups as $ag) {
                            foreach ($ag->attrs as $attr) {
                                $name = $attr->name->toString();
                                $resolved = $imports[$name] ?? $name;
                                if (str_ends_with($resolved, '\\Hook') || $resolved === 'Hook') {
                                    $hasHook = true;
                                    $hookArg = $attr->args[0] ?? null;
                                    if ($hookArg && $hookArg->value instanceof Node\Scalar\String_) {
                                        $hName = $hookArg->value->value;
                                        if (in_array($hName, ['init_startup', 'thread_post_complete', 'global_bootstrap'], true)) {
                                            $errors[] = "Deprecated/invalid hook '{$hName}' on {$cls->name?->name}::{$method->name->name}() in {$file}. Use real vB6 hook names.";
                                        } elseif (in_array($hName, $validTemplateHooks, true)) {
                                            $errors[] = "Template hook '{$hName}' found in #[Hook] attribute on {$cls->name?->name}::{$method->name->name}() in {$file}. Place under templates/hooks/<hookname>.html instead.";
                                        } elseif (!empty($validPhpHooks) && !in_array($hName, $validPhpHooks, true)) {
                                            $closest = self::suggestClosest($hName, $validPhpHooks);
                                            $sug = $closest ? " Did you mean '{$closest}'?" : "";
                                            $errors[] = "Unknown PHP hook '{$hName}' on {$cls->name?->name}::{$method->name->name}() in {$file}.{$sug}";
                                        }
                                    }
                                } elseif (str_ends_with($resolved, '\\Api') || $resolved === 'Api') {
                                    $hasApi = true;
                                } elseif (str_ends_with($resolved, '\\Can') || $resolved === 'Can') {
                                    $hasCan = true;
                                } elseif (!in_array($resolved, ['Override', 'ReturnTypeWillChange', 'SensitiveParameter', 'Deprecated'], true)) {
                                    $errors[] = "Unknown attribute '#[{$name}]' on {$cls->name?->name}::{$method->name->name}() in {$file}.";
                                }
                            }
                        }

                        if ($hasHook) {
                            // Validate by-reference array &$params parameter
                            $methodParams = $method->getParams();
                            $validByRef = false;
                            if (!empty($methodParams)) {
                                $p0 = $methodParams[0];
                                if ($p0->byRef) {
                                    $validByRef = true;
                                }
                            }
                            if (!$validByRef) {
                                $errors[] = "Hook handler {$cls->name?->name}::{$method->name->name}() in {$file} must accept an array by reference: 'public function {$method->name->name}(array &\$params): void'.";
                            }
                        }

                        if ($hasCan && !$hasApi) {
                            $errors[] = "#[Can] attribute found on {$cls->name?->name}::{$method->name->name}() in {$file} without #[Api]. #[Can] is only permitted on #[Api] endpoints.";
                        }
                    }
                }
            }
        }

        // Validate template hooks in templates/hooks/
        $tplHooksDir = $addonPath . '/templates/hooks';
        if (is_dir($tplHooksDir)) {
            $tplHookFiles = glob($tplHooksDir . '/*.html') ?: [];
            foreach ($tplHookFiles as $thFile) {
                $thName = basename($thFile, '.html');
                if (!empty($validTemplateHooks) && !in_array($thName, $validTemplateHooks, true)) {
                    $closest = self::suggestClosest($thName, $validTemplateHooks);
                    $sug = $closest ? " Did you mean '{$closest}'?" : "";
                    $errors[] = "Unknown template hook '{$thName}' in {$thFile}.{$sug}";
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
