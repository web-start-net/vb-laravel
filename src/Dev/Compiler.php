<?php

declare(strict_types=1);

namespace VbLaravel\Dev;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

final class Compiler
{
    private static ?Standard $printer = null;

    public static function build(string $addonPath, ?string $outputPath = null): string
    {
        if (!class_exists(ParserFactory::class)) {
            $devAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (is_file($devAutoload)) {
                require_once $devAutoload;
            }
        }

        $addonPath = realpath($addonPath) ?: rtrim($addonPath, '/\\');
        $errors = Validator::validate($addonPath);
        if (!empty($errors)) {
            throw new RuntimeException("Build validation failed:\n - " . implode("\n - ", $errors));
        }

        $manifest = json_decode((string) file_get_contents($addonPath . '/vb-addon.json'), true);
        $id = (string) $manifest['id'];
        $title = (string) ($manifest['title'] ?? $manifest['name']);
        $version = (string) $manifest['version'];
        $description = (string) ($manifest['description'] ?? '');

        $targetPkgDir = $outputPath
            ? rtrim($outputPath, '/\\')
            : $addonPath . '/package/core/packages/' . $id;

        // 0. Clean target directory before build (Item 8)
        if (is_dir($targetPkgDir)) {
            self::deleteDirectory($targetPkgDir);
        }
        mkdir($targetPkgDir, 0777, true);

        // 1. Process templates and template hooks (Item 8, 9)
        $templatesXml = '';
        $templateHooksXml = '';
        $templatesDir = $addonPath . '/templates';
        if (is_dir($templatesDir)) {
            // General templates
            foreach (glob($templatesDir . '/*.html') ?: [] as $tplFile) {
                $tplName = basename($tplFile, '.html');
                $tplContent = self::escapeCdata((string) file_get_contents($tplFile));
                $fullTplName = $id . '_' . $tplName;
                $templatesXml .= "        <template name=\"{$fullTplName}\" templatetype=\"template\" date=\"1786800000\" username=\"VB Laravel\" version=\"{$version}\"><![CDATA[{$tplContent}]]></template>\n";
            }

            // Template hooks (templates/hooks/<hookname>.html)
            $tplHooksDir = $templatesDir . '/hooks';
            if (is_dir($tplHooksDir)) {
                foreach (glob($tplHooksDir . '/*.html') ?: [] as $hookTplFile) {
                    $hookName = basename($hookTplFile, '.html');
                    $hookContent = self::escapeCdata((string) file_get_contents($hookTplFile));
                    $fullHookTplName = $id . '_hook_' . $hookName;
                    $templatesXml .= "        <template name=\"{$fullHookTplName}\" templatetype=\"template\" date=\"1786800000\" username=\"VB Laravel\" version=\"{$version}\"><![CDATA[{$hookContent}]]></template>\n";
                    $templateHooksXml .= "        <hook>\n" .
                        "            <hookname>{$hookName}</hookname>\n" .
                        "            <title>{$title} - {$hookName}</title>\n" .
                        "            <active>1</active>\n" .
                        "            <hookorder>10</hookorder>\n" .
                        "            <template>{$fullHookTplName}</template>\n" .
                        "            <arguments />\n" .
                        "        </hook>\n";
                }
            }
        }

        // 2. Process settings (options XML) and settings phrases (Item 1)
        $settingsXml = '';
        $settingsPhrases = [];
        $settingsFile = $addonPath . '/config/settings.php';
        if (is_file($settingsFile)) {
            $settingsData = require $settingsFile;
            if (is_array($settingsData) && !empty($settingsData['settings'])) {
                $groupName = $id;
                $groupDisplayOrder = (int) ($settingsData['displayorder'] ?? 1000);
                $groupTitle = (string) ($settingsData['group_title'] ?? $title);

                $settingsPhrases["settinggroup_{$groupName}"] = $groupTitle;

                $settingsXml .= "    <options>\n";
                $settingsXml .= "        <settinggroup name=\"{$groupName}\" displayorder=\"{$groupDisplayOrder}\">\n";

                $settingOrder = 10;
                foreach ($settingsData['settings'] as $varname => $cfg) {
                    $order = (int) ($cfg['displayorder'] ?? $settingOrder);
                    $settingOrder = $order + 10;

                    $rawType = strtolower((string) ($cfg['datatype'] ?? ($cfg['type'] ?? 'free')));
                    $datatype = match ($rawType) {
                        'boolean', 'bool' => 'boolean',
                        'integer', 'int', 'number' => 'integer',
                        default => 'free',
                    };

                    $defaultOptionCode = match ($datatype) {
                        'boolean' => 'yesno',
                        'integer' => 'input:5',
                        default => 'input:30',
                    };
                    $optioncode = (string) ($cfg['optioncode'] ?? $defaultOptionCode);
                    $def = (string) ($cfg['default'] ?? ($cfg['defaultvalue'] ?? ''));

                    $settingsXml .= "            <setting varname=\"{$varname}\" displayorder=\"{$order}\">\n";
                    $settingsXml .= "                <datatype>{$datatype}</datatype>\n";
                    $settingsXml .= "                <optioncode>{$optioncode}</optioncode>\n";
                    $settingsXml .= "                <defaultvalue>" . htmlspecialchars($def, ENT_XML1) . "</defaultvalue>\n";
                    $settingsXml .= "            </setting>\n";

                    if (!empty($cfg['title'])) {
                        $settingsPhrases["setting_{$varname}_title"] = (string) $cfg['title'];
                    }
                    if (!empty($cfg['description'])) {
                        $settingsPhrases["setting_{$varname}_desc"] = (string) $cfg['description'];
                    }
                }

                $settingsXml .= "        </settinggroup>\n";
                $settingsXml .= "    </options>\n";
            }
        }

        // 3. Process phrases (Item 8: Frontend phrases -> 'global', settings -> 'vbsettings')
        $globalPhrases = [];
        $langFile = $addonPath . '/lang/en.php';
        if (is_file($langFile)) {
            $langData = require $langFile;
            if (is_array($langData)) {
                foreach ($langData as $pKey => $pVal) {
                    if (str_starts_with($pKey, 'setting_') || str_starts_with($pKey, 'settinggroup_')) {
                        $settingsPhrases[$pKey] = (string) $pVal;
                    } else {
                        $globalPhrases[$pKey] = (string) $pVal;
                    }
                }
            }
        }

        $phrasesXml = '';
        if (!empty($settingsPhrases) || !empty($globalPhrases)) {
            $phrasesXml .= "    <phrases>\n";
            if (!empty($globalPhrases)) {
                $phrasesXml .= "        <phrasetype name=\"GLOBAL\" fieldname=\"global\">\n";
                foreach ($globalPhrases as $pKey => $pVal) {
                    $pKeyEsc = htmlspecialchars($pKey, ENT_XML1);
                    $pValEsc = self::escapeCdata($pVal);
                    $phrasesXml .= "            <phrase name=\"{$pKeyEsc}\" date=\"1786800000\" username=\"VB Laravel\" version=\"{$version}\"><![CDATA[{$pValEsc}]]></phrase>\n";
                }
                $phrasesXml .= "        </phrasetype>\n";
            }
            if (!empty($settingsPhrases)) {
                $phrasesXml .= "        <phrasetype name=\"vBulletin Settings\" fieldname=\"vbsettings\">\n";
                foreach ($settingsPhrases as $pKey => $pVal) {
                    $pKeyEsc = htmlspecialchars($pKey, ENT_XML1);
                    $pValEsc = self::escapeCdata($pVal);
                    $phrasesXml .= "            <phrase name=\"{$pKeyEsc}\" date=\"1786800000\" username=\"VB Laravel\" version=\"{$version}\"><![CDATA[{$pValEsc}]]></phrase>\n";
                }
                $phrasesXml .= "        </phrasetype>\n";
            }
            $phrasesXml .= "    </phrases>\n";
        }

        // 4. Copy addon runtime assets (src, database, vb-addon.json) to output package directory FIRST
        self::copyPackageAssets($addonPath, $targetPkgDir);

        // 5. AST-based discovery of PHP Hooks and APIs in src/ (Item 3, 4, 5)
        $phpHooks = self::discoverPhpHooks($addonPath . '/src');
        $apis = self::discoverApis($addonPath . '/src');

        // 6. Build Generated Package Glue (product.php, hooks.php, HookDispatcher.php ONLY in output pkg, api/*.php)
        self::generatePackageGlue($targetPkgDir, $id, $manifest, $phpHooks, $apis);

        // 7. Build product XML
        $xmlContent = self::renderProductXml(
            $id,
            $title,
            $version,
            $description,
            $templatesXml,
            $templateHooksXml,
            $settingsXml,
            $phrasesXml
        );

        $xmlDir = $targetPkgDir . '/xml';
        if (!is_dir($xmlDir)) {
            mkdir($xmlDir, 0777, true);
        }
        $xmlFile = $xmlDir . '/product_' . $id . '.xml';
        file_put_contents($xmlFile, $xmlContent);

        return $xmlFile;
    }

    private static function parsePhpFile(string $filePath): array
    {
        if (!class_exists(ParserFactory::class)) {
            throw new RuntimeException("nikic/php-parser is required for building vBulletin add-ons. Run composer install.");
        }
        $code = (string) file_get_contents($filePath);
        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $stmts = $parser->parse($code);
            return $stmts ?? [];
        } catch (\Throwable $e) {
            throw new RuntimeException("Parse error in {$filePath}: " . $e->getMessage(), 0, $e);
        }
    }

    public static function discoverPhpHooks(string $srcDir): array
    {
        $discovered = self::analyzeSourceTree($srcDir);
        return $discovered['hooks'];
    }

    public static function discoverApis(string $srcDir): array
    {
        $discovered = self::analyzeSourceTree($srcDir);
        return $discovered['apis'];
    }

    private static function analyzeSourceTree(string $srcDir): array
    {
        $allHooks = [];
        $allApis = [];

        if (!is_dir($srcDir)) {
            return ['hooks' => $allHooks, 'apis' => $allApis];
        }

        self::$printer ??= new Standard();

        $files = self::rglob($srcDir, '*.php');
        foreach ($files as $file) {
            // Skip any generated directory
            if (str_contains(str_replace('\\', '/', $file), '/_Generated/')) {
                continue;
            }

            $stmts = self::parsePhpFile($file);
            $currentNs = '';
            $imports = [];

            foreach ($stmts as $stmt) {
                if ($stmt instanceof Stmt\Namespace_) {
                    $currentNs = $stmt->name ? $stmt->name->toString() : '';
                    foreach ($stmt->stmts as $inner) {
                        if ($inner instanceof Stmt\Use_) {
                            foreach ($inner->uses as $u) {
                                $alias = $u->alias ? $u->alias->name : $u->name->getLast();
                                $imports[$alias] = $u->name->toString();
                            }
                        } elseif ($inner instanceof Stmt\Class_) {
                            self::analyzeClass($inner, $currentNs, $imports, $file, $allHooks, $allApis);
                        }
                    }
                } elseif ($stmt instanceof Stmt\Use_) {
                    foreach ($stmt->uses as $u) {
                        $alias = $u->alias ? $u->alias->name : $u->name->getLast();
                        $imports[$alias] = $u->name->toString();
                    }
                } elseif ($stmt instanceof Stmt\Class_) {
                    self::analyzeClass($stmt, $currentNs, $imports, $file, $allHooks, $allApis);
                }
            }
        }

        // Sort hook handlers by order (Item 8)
        foreach ($allHooks as $hookName => &$handlers) {
            usort($handlers, static function (array $a, array $b): int {
                return ($a['order'] ?? 10) <=> ($b['order'] ?? 10);
            });
        }
        unset($handlers);

        return ['hooks' => $allHooks, 'apis' => $allApis];
    }

    private static function analyzeClass(
        Stmt\Class_ $class,
        string $currentNs,
        array $imports,
        string $filePath,
        array &$hooks,
        array &$apis
    ): void {
        if ($class->name === null) {
            return;
        }

        $className = $class->name->name;
        $classFqcn = $currentNs !== '' ? $currentNs . '\\' . $className : $className;

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic()) {
                continue;
            }

            $methodName = $method->name->name;
            $hookAttr = null;
            $apiAttr = null;
            $canAttr = null;

            foreach ($method->attrGroups as $group) {
                foreach ($group->attrs as $attr) {
                    $rawName = $attr->name->toString();
                    $resolved = self::resolveAttributeName($rawName, $imports, $currentNs);

                    if ($resolved === 'Hook' || $resolved === 'VbLaravel\Attributes\Hook') {
                        $hookAttr = self::parseHookAttribute($attr, $classFqcn, $methodName, $filePath);
                    } elseif ($resolved === 'Api' || $resolved === 'VbLaravel\Attributes\Api') {
                        $apiAttr = self::parseApiAttribute($attr, $classFqcn, $methodName, $filePath);
                    } elseif ($resolved === 'Can' || $resolved === 'VbLaravel\Attributes\Can') {
                        $canAttr = self::parseCanAttribute($attr, $classFqcn, $methodName, $filePath);
                    } elseif (in_array($resolved, ['Override', 'ReturnTypeWillChange', 'SensitiveParameter', 'Deprecated'], true)) {
                        continue;
                    } else {
                        // Item 3: Unknown attribute = build ERROR
                        throw new RuntimeException("Unknown attribute '#[{$rawName}]' on {$classFqcn}::{$methodName}() in {$filePath}. Allowed attributes: #[Hook], #[Api], #[Can].");
                    }
                }
            }

            // Item 5: #[Can] only allowed on #[Api]
            if ($canAttr !== null && $apiAttr === null) {
                throw new RuntimeException("#[Can] attribute on {$classFqcn}::{$methodName}() in {$filePath} is only allowed on #[Api] endpoints.");
            }

            if ($hookAttr !== null) {
                $hooks[$hookAttr['name']][] = [
                    'class' => $classFqcn,
                    'method' => $methodName,
                    'order' => $hookAttr['order'],
                ];
            }

            if ($apiAttr !== null) {
                // Item 4: Keep API params exactly as declared (no injected defaults)
                $paramSignatures = [];
                $callArgs = [];
                $hasSecurityTokenParam = false;

                foreach ($method->params as $param) {
                    $paramSignatures[] = trim(self::$printer->prettyPrint([$param]));
                    $paramName = $param->var->name;
                    $callArgs[] = '$' . $paramName;
                    if ($paramName === 'securitytoken') {
                        $hasSecurityTokenParam = true;
                    }
                }

                $returnType = $method->returnType ? trim(self::$printer->prettyPrint([$method->returnType])) : 'mixed';

                $apis[$apiAttr['controller']][] = [
                    'class' => $classFqcn,
                    'method' => $methodName,
                    'action' => $apiAttr['action'],
                    'write' => $apiAttr['write'],
                    'guest' => $apiAttr['guest'],
                    'paramSignatures' => $paramSignatures,
                    'callArgs' => $callArgs,
                    'hasSecurityTokenParam' => $hasSecurityTokenParam,
                    'returnType' => $returnType,
                    'canPerm' => $canAttr ? $canAttr['permission'] : null,
                    'canGroup' => $canAttr ? $canAttr['group'] : 'genericpermissions',
                ];
            }
        }
    }

    private static function parseHookAttribute(Node\Attribute $attr, string $classFqcn, string $methodName, string $filePath): array
    {
        $name = null;
        $order = 10;

        foreach ($attr->args as $i => $arg) {
            $argName = $arg->name ? $arg->name->name : null;
            $val = self::evaluateScalarExpr($arg->value, $classFqcn, $methodName, 'Hook', $filePath);

            if ($argName === 'name' || ($argName === null && $i === 0)) {
                if (!is_string($val) || $val === '') {
                    throw new RuntimeException("Invalid hook name in #[Hook] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $name = $val;
            } elseif ($argName === 'order' || ($argName === null && $i === 1)) {
                if (!is_int($val)) {
                    throw new RuntimeException("Invalid order in #[Hook] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $order = $val;
            } else {
                throw new RuntimeException("Unknown argument '{$argName}' in #[Hook] on {$classFqcn}::{$methodName}() in {$filePath}.");
            }
        }

        if ($name === null) {
            throw new RuntimeException("Missing hook name argument in #[Hook] on {$classFqcn}::{$methodName}() in {$filePath}.");
        }

        return ['name' => $name, 'order' => $order];
    }

    private static function parseApiAttribute(Node\Attribute $attr, string $classFqcn, string $methodName, string $filePath): array
    {
        $controller = null;
        $action = null;
        $write = false;
        $guest = false;

        foreach ($attr->args as $i => $arg) {
            $argName = $arg->name ? $arg->name->name : null;
            $val = self::evaluateScalarExpr($arg->value, $classFqcn, $methodName, 'Api', $filePath);

            if ($argName === 'controller' || ($argName === null && $i === 0)) {
                if (!is_string($val) || $val === '') {
                    throw new RuntimeException("Invalid controller in #[Api] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $controller = $val;
            } elseif ($argName === 'action' || $argName === 'method' || ($argName === null && $i === 1)) {
                if (!is_string($val) || $val === '') {
                    throw new RuntimeException("Invalid action in #[Api] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $action = $val;
            } elseif ($argName === 'write' || ($argName === null && $i === 2)) {
                if (!is_bool($val)) {
                    throw new RuntimeException("Invalid write flag (must be boolean) in #[Api] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $write = $val;
            } elseif ($argName === 'guest' || ($argName === null && $i === 3)) {
                if (!is_bool($val)) {
                    throw new RuntimeException("Invalid guest flag (must be boolean) in #[Api] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $guest = $val;
            } else {
                throw new RuntimeException("Unknown argument '{$argName}' in #[Api] on {$classFqcn}::{$methodName}() in {$filePath}.");
            }
        }

        if ($controller === null || $action === null) {
            throw new RuntimeException("Missing required controller or action in #[Api] on {$classFqcn}::{$methodName}() in {$filePath}.");
        }

        return ['controller' => $controller, 'action' => $action, 'write' => $write, 'guest' => $guest];
    }

    private static function parseCanAttribute(Node\Attribute $attr, string $classFqcn, string $methodName, string $filePath): array
    {
        $permission = null;
        $group = 'genericpermissions';

        foreach ($attr->args as $i => $arg) {
            $argName = $arg->name ? $arg->name->name : null;
            $val = self::evaluateScalarExpr($arg->value, $classFqcn, $methodName, 'Can', $filePath);

            if ($argName === 'permission' || ($argName === null && $i === 0)) {
                if (!is_string($val) || $val === '') {
                    throw new RuntimeException("Invalid permission in #[Can] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $permission = $val;
            } elseif ($argName === 'group' || ($argName === null && $i === 1)) {
                if (!is_string($val) || $val === '') {
                    throw new RuntimeException("Invalid group in #[Can] on {$classFqcn}::{$methodName}() in {$filePath}.");
                }
                $group = $val;
            } else {
                throw new RuntimeException("Unknown argument '{$argName}' in #[Can] on {$classFqcn}::{$methodName}() in {$filePath}.");
            }
        }

        if ($permission === null) {
            throw new RuntimeException("Missing required permission in #[Can] on {$classFqcn}::{$methodName}() in {$filePath}.");
        }

        return ['permission' => $permission, 'group' => $group];
    }

    private static function evaluateScalarExpr(Node\Expr $expr, string $classFqcn, string $methodName, string $attrName, string $filePath): mixed
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }
        if ($expr instanceof Node\Scalar\Int_) {
            return $expr->value;
        }
        if ($expr instanceof Node\Expr\ConstFetch) {
            $constName = strtolower($expr->name->toString());
            if ($constName === 'true') {
                return true;
            }
            if ($constName === 'false') {
                return false;
            }
            if ($constName === 'null') {
                return null;
            }
        }

        throw new RuntimeException("Unparseable or unsupported expression in #[{$attrName}] on {$classFqcn}::{$methodName}() in {$filePath}.");
    }

    private static function resolveAttributeName(string $name, array $imports, string $currentNs): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name);
        $first = $parts[0];
        if (isset($imports[$first])) {
            $parts[0] = $imports[$first];
            return implode('\\', $parts);
        }

        if ($currentNs !== '') {
            $candidate = $currentNs . '\\' . $name;
            if (in_array($name, ['Hook', 'Api', 'Can'], true)) {
                return $name;
            }
            return $candidate;
        }

        return $name;
    }

    private static function generatePackageGlue(
        string $pkgDir,
        string $id,
        array $manifest,
        array $phpHooks,
        array $apis
    ): void {
        $productClass = $id . '_Product';
        $hooksClass = $id . '_Hooks';

        $psr4 = $manifest['autoload']['psr-4'] ?? [$id . '\\' => 'src/'];
        $psr4Export = var_export($psr4, true);

        // 1. product.php with lazy provider registration (Item 7)
        $registerProviderCode = '';
        if (!empty($manifest['provider'])) {
            $provEsc = addslashes((string) $manifest['provider']);
            $registerProviderCode = "\\VbLaravel\\Runtime::registerProvider('{$provEsc}');\n";
        }

        $productCode = <<<PHP
<?php

declare(strict_types=1);

require_once DIR . '/packages/vblaravel/bootstrap.php';

spl_autoload_register(static function (string \$class): void {
    if (strncasecmp(\$class, '{$id}\\\\_Generated\\\\', strlen('{$id}\\\\_Generated\\\\')) === 0) {
        \$rel = substr(\$class, strlen('{$id}\\\\_Generated\\\\'));
        \$file = __DIR__ . '/src/_Generated/' . str_replace('\\\\', '/', \$rel) . '.php';
        if (is_file(\$file)) {
            require_once \$file;
            return;
        }
    }
    \$map = {$psr4Export};
    foreach (\$map as \$prefix => \$path) {
        if (strncasecmp(\$class, \$prefix, strlen(\$prefix)) === 0) {
            \$rel = substr(\$class, strlen(\$prefix));
            \$file = __DIR__ . '/' . rtrim(\$path, '/\\\\') . '/' . str_replace('\\\\', '/', \$rel) . '.php';
            if (is_file(\$file)) {
                require_once \$file;
                return;
            }
        }
    }
});

{$registerProviderCode}
class {$productClass}
{
    public \$vbMinVersion = '6.2.2';
    public \$vbMaxVersion = '6.9.9';
    public static \$AutoInstall = false;
    public \$hookClasses = [
        '{$hooksClass}',
    ];
}

PHP;
        file_put_contents($pkgDir . '/product.php', $productCode);

        // 2. HookDispatcher.php ONLY in output package dir (Item 8)
        $genDir = $pkgDir . '/src/_Generated';
        if (!is_dir($genDir)) {
            mkdir($genDir, 0777, true);
        }

        $dispatcherMethods = '';
        $hooksMethods = '';

        foreach ($phpHooks as $hookName => $handlers) {
            $handlerCalls = '';
            foreach ($handlers as $h) {
                $handlerCalls .= "        (new \\{$h['class']}())->{$h['method']}(\$params);\n";
            }

            $dispatcherMethods .= "    public static function {$hookName}(array &\$params): void\n    {\n{$handlerCalls}    }\n\n";

            // Item 2: NO Runtime::boot() in hooks.php!
            $hooksMethods .= "    public static function {$hookName}(array &\$params): void\n";
            $hooksMethods .= "    {\n";
            $hooksMethods .= "        \\{$id}\\_Generated\\HookDispatcher::{$hookName}(\$params);\n";
            $hooksMethods .= "    }\n\n";
        }

        $dispatcherCode = <<<PHP
<?php

declare(strict_types=1);

namespace {$id}\\_Generated;

class HookDispatcher
{
{$dispatcherMethods}}

PHP;
        file_put_contents($genDir . '/HookDispatcher.php', $dispatcherCode);

        // 3. hooks.php
        $hooksCode = <<<PHP
<?php

declare(strict_types=1);

require_once __DIR__ . '/product.php';

class {$hooksClass}
{
    public static \$order = 10;

{$hooksMethods}}

PHP;
        file_put_contents($pkgDir . '/hooks.php', $hooksCode);

        // 4. API Controllers in output package api/
        $apiDir = $pkgDir . '/api';
        if (!is_dir($apiDir)) {
            mkdir($apiDir, 0777, true);
        }

        foreach ($apis as $ctrlIdent => $actions) {
            $parts = explode(':', $ctrlIdent);
            $ctrlName = end($parts);
            $apiClassName = $id . '_Api_' . ucfirst($ctrlName);

            $apiMethods = '';
            foreach ($actions as $act) {
                // Item 4: Keep exact param signatures
                $sig = implode(', ', $act['paramSignatures']);
                $ret = $act['returnType'] !== 'mixed' ? ': ' . $act['returnType'] : '';
                $callArgs = implode(', ', $act['callArgs']);

                $apiMethods .= "    public function {$act['action']}({$sig}){$ret}\n    {\n";

                // Item 2: NO Runtime::boot() in api wrappers!

                if ($act['canPerm']) {
                    $pEsc = addslashes($act['canPerm']);
                    $gEsc = addslashes($act['canGroup']);
                    $apiMethods .= "        if (!vb()->can('{$pEsc}', '{$gEsc}')) {\n";
                    $apiMethods .= "            throw new \\vB_Exception_Api('no_permission');\n";
                    $apiMethods .= "        }\n\n";
                }

                // Write endpoints reject unauthenticated users (userid 0) by default (guest: false)
                // Note: vB6 automatically checks securitytoken (CSRF) for all POST /ajax/api calls at the
                // frontend application gateway (vB5_Frontend_ApplicationLight::execute() -> checkCSRF()).
                if ($act['write'] && !$act['guest']) {
                    $apiMethods .= "        if (vb()->userId() === 0) {\n";
                    $apiMethods .= "            throw new \\vB_Exception_Api('no_permission');\n";
                    $apiMethods .= "        }\n\n";
                }

                $apiMethods .= "        \$controller = new \\{$act['class']}();\n";
                $apiMethods .= "        return \$controller->{$act['method']}({$callArgs});\n";
                $apiMethods .= "    }\n\n";
            }

            $apiCode = "<?php\n\ndeclare(strict_types=1);\n\nrequire_once dirname(__DIR__) . '/product.php';\n\nclass {$apiClassName} extends \\vB_Api\n{\n{$apiMethods}}\n";
            file_put_contents($apiDir . '/' . strtolower($ctrlName) . '.php', $apiCode);
        }
    }

    private static function copyPackageAssets(string $addonPath, string $pkgDir): void
    {
        // Copy src/
        $srcDir = $addonPath . '/src';
        if (is_dir($srcDir) && realpath($srcDir) !== realpath($pkgDir . '/src')) {
            self::copyDirectory($srcDir, $pkgDir . '/src', ['_Generated']);
        }

        // Copy database/
        $dbDir = $addonPath . '/database';
        if (is_dir($dbDir) && realpath($dbDir) !== realpath($pkgDir . '/database')) {
            self::copyDirectory($dbDir, $pkgDir . '/database');
        }

        // Copy vb-addon.json
        $manifest = $addonPath . '/vb-addon.json';
        if (is_file($manifest) && realpath($manifest) !== realpath($pkgDir . '/vb-addon.json')) {
            copy($manifest, $pkgDir . '/vb-addon.json');
        }
    }

    private static function copyDirectory(string $source, string $destination, array $excludeDirs = []): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }
        $dir = opendir($source);
        if (!$dir) {
            return;
        }
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (in_array($file, $excludeDirs, true)) {
                continue;
            }
            $srcPath = $source . '/' . $file;
            $dstPath = $destination . '/' . $file;
            if (is_dir($srcPath)) {
                self::copyDirectory($srcPath, $dstPath, $excludeDirs);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private static function escapeCdata(string $content): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $content);
    }

    private static function renderProductXml(
        string $id,
        string $title,
        string $version,
        string $description,
        string $templatesXml,
        string $templateHooksXml,
        string $settingsXml,
        string $phrasesXml
    ): string {
        $titleEsc = htmlspecialchars($title, ENT_XML1);
        $descEsc = htmlspecialchars($description, ENT_XML1);

        $installCode = self::escapeCdata(
            "\$vblBootstrap = DIR . '/packages/vblaravel/bootstrap.php';\n" .
            "if (is_file(\$vblBootstrap)) {\n" .
            "    require_once \$vblBootstrap;\n" .
            "}\n" .
            "if (class_exists('\\VbLaravel\\Migrator')) {\n" .
            "    \\VbLaravel\\Migrator::up(DIR . '/packages/{$id}');\n" .
            "}\n"
        );

        $uninstallCode = self::escapeCdata(
            "\$vblBootstrap = DIR . '/packages/vblaravel/bootstrap.php';\n" .
            "if (is_file(\$vblBootstrap)) {\n" .
            "    require_once \$vblBootstrap;\n" .
            "}\n" .
            "if (class_exists('\\VbLaravel\\Migrator')) {\n" .
            "    \\VbLaravel\\Migrator::down(DIR . '/packages/{$id}');\n" .
            "}\n"
        );

        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        $xml .= "<product productid=\"{$id}\" active=\"1\">\n";
        $xml .= "    <title>{$titleEsc}</title>\n";
        $xml .= "    <description>{$descEsc}</description>\n";
        $xml .= "    <version>{$version}</version>\n";
        $xml .= "    <url></url>\n";
        $xml .= "    <versioncheckurl></versioncheckurl>\n";
        $xml .= "    <dependencies>\n";
        $xml .= "        <dependency dependencytype=\"product\" parentproductid=\"vblaravel\" minversion=\"0.2.0\" maxversion=\"\" />\n";
        $xml .= "    </dependencies>\n";
        $xml .= "    <codes>\n";
        $xml .= "        <code version=\"*\">\n";
        $xml .= "            <installcode><![CDATA[{$installCode}]]></installcode>\n";
        $xml .= "            <uninstallcode><![CDATA[{$uninstallCode}]]></uninstallcode>\n";
        $xml .= "        </code>\n";
        $xml .= "    </codes>\n";
        if ($templatesXml) {
            $xml .= "    <templates>\n{$templatesXml}    </templates>\n";
        }
        if ($templateHooksXml) {
            $xml .= "    <hooks>\n{$templateHooksXml}    </hooks>\n";
        }
        if ($settingsXml) {
            $xml .= "{$settingsXml}";
        }
        if ($phrasesXml) {
            $xml .= "{$phrasesXml}";
        }
        $xml .= "</product>\n";

        return $xml;
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
