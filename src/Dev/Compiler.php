<?php

declare(strict_types=1);

namespace VbLaravel\Dev;

use RuntimeException;

final class Compiler
{
    public static function build(string $addonPath, ?string $outputPath = null): string
    {
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

        if (!is_dir($targetPkgDir)) {
            mkdir($targetPkgDir, 0777, true);
        }

        // 1. Process templates
        $templatesXml = '';
        $templateHooksXml = '';
        $templatesDir = $addonPath . '/templates';
        if (is_dir($templatesDir)) {
            // General templates
            foreach (glob($templatesDir . '/*.html') ?: [] as $tplFile) {
                $tplName = basename($tplFile, '.html');
                $tplContent = (string) file_get_contents($tplFile);
                $fullTplName = $id . '_' . $tplName;
                $templatesXml .= "        <template name=\"{$fullTplName}\" templatetype=\"template\" date=\"1786800000\" username=\"VB Laravel\" version=\"{$version}\"><![CDATA[{$tplContent}]]></template>\n";
            }

            // Template hooks (templates/hooks/<hookname>.html)
            $tplHooksDir = $templatesDir . '/hooks';
            if (is_dir($tplHooksDir)) {
                foreach (glob($tplHooksDir . '/*.html') ?: [] as $hookTplFile) {
                    $hookName = basename($hookTplFile, '.html');
                    $hookContent = (string) file_get_contents($hookTplFile);
                    $fullHookTplName = $id . '_hook_' . $hookName;
                    $templatesXml .= "        <template name=\"{$fullHookTplName}\" templatetype=\"template\" date=\"1786800000\" username=\"VB Laravel\" version=\"{$version}\"><![CDATA[{$hookContent}]]></template>\n";
                    $templateHooksXml .= "        <hook>\n" .
                        "            <hookname>{$hookName}</hookname>\n" .
                        "            <title>{$title}</title>\n" .
                        "            <active>1</active>\n" .
                        "            <hookorder>10</hookorder>\n" .
                        "            <template>{$fullHookTplName}</template>\n" .
                        "            <arguments />\n" .
                        "        </hook>\n";
                }
            }
        }

        // 2. Process settings
        $settingsXml = '';
        $settingsFile = $addonPath . '/config/settings.php';
        if (is_file($settingsFile)) {
            $settingsData = require $settingsFile;
            if (is_array($settingsData) && !empty($settingsData['settings'])) {
                $groupTitle = htmlspecialchars($settingsData['group_title'] ?? $title, ENT_XML1);
                $settingsXml .= "    <settinggroups>\n";
                $settingsXml .= "        <settinggroup name=\"{$id}\" displayorder=\"100\">\n";
                $settingsXml .= "            <title>{$groupTitle}</title>\n";
                $settingsXml .= "        </settinggroup>\n";
                $settingsXml .= "    </settinggroups>\n";
                $settingsXml .= "    <settings>\n";
                $order = 10;
                foreach ($settingsData['settings'] as $varname => $cfg) {
                    $def = htmlspecialchars((string) ($cfg['default'] ?? ''), ENT_XML1);
                    $optType = htmlspecialchars((string) ($cfg['type'] ?? 'text'), ENT_XML1);
                    $settingsXml .= "        <setting varname=\"{$varname}\" displayorder=\"{$order}\">\n";
                    $settingsXml .= "            <settinggroup>{$id}</settinggroup>\n";
                    $settingsXml .= "            <type>{$optType}</type>\n";
                    $settingsXml .= "            <value>{$def}</value>\n";
                    $settingsXml .= "            <defaultvalue>{$def}</defaultvalue>\n";
                    $settingsXml .= "        </setting>\n";
                    $order += 10;
                }
                $settingsXml .= "    </settings>\n";
            }
        }

        // 3. Process phrases
        $phrasesXml = '';
        $langFile = $addonPath . '/lang/en.php';
        if (is_file($langFile)) {
            $langData = require $langFile;
            if (is_array($langData)) {
                $phrasesXml .= "    <phrases>\n";
                $phrasesXml .= "        <phrasetype name=\"vbulletin_settings\" fieldname=\"vbsettings\">\n";
                foreach ($langData as $pKey => $pVal) {
                    $pKeyEsc = htmlspecialchars($pKey, ENT_XML1);
                    $phrasesXml .= "            <phrase name=\"{$pKeyEsc}\" date=\"1786800000\" username=\"VB Laravel\" version=\"{$version}\"><![CDATA[{$pVal}]]></phrase>\n";
                }
                $phrasesXml .= "        </phrasetype>\n";
                $phrasesXml .= "    </phrases>\n";
            }
        }

        // 4. Inspect PHP Hooks and APIs in src/
        $phpHooks = self::discoverPhpHooks($addonPath . '/src');
        $apis = self::discoverApis($addonPath . '/src');

        // 5. Build Generated Package Glue (product.php, hooks.php, HookDispatcher.php, api/*.php)
        self::generatePackageGlue($targetPkgDir, $addonPath, $id, $manifest, $phpHooks, $apis);

        // 6. Copy addon runtime assets to target package directory
        self::copyPackageAssets($addonPath, $targetPkgDir);

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

    private static function discoverPhpHooks(string $srcDir): array
    {
        $hooks = [];
        if (!is_dir($srcDir)) {
            return $hooks;
        }

        $files = self::rglob($srcDir, '*.php');

        foreach ($files as $file) {
            $code = (string) file_get_contents($file);
            if (preg_match('/namespace\s+([^;]+);/', $code, $nsMatch)) {
                $ns = trim($nsMatch[1]);
            } else {
                $ns = '';
            }

            if (preg_match('/class\s+([A-Za-z0-9_]+)/', $code, $clsMatch)) {
                $className = $ns ? $ns . '\\' . $clsMatch[1] : $clsMatch[1];
            } else {
                continue;
            }

            if (preg_match_all('/#\[Hook\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*(\d+))?\s*\)\]\s*(?:#\[[^\]]+\]\s*)*public\s+function\s+([A-Za-z0-9_]+)\s*\(/', $code, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $hookName = $m[1];
                    $order = !empty($m[2]) ? (int) $m[2] : 10;
                    $methodName = $m[3];

                    $hooks[$hookName][] = [
                        'class' => $className,
                        'method' => $methodName,
                        'order' => $order,
                    ];
                }
            }
        }

        return $hooks;
    }

    private static function discoverApis(string $srcDir): array
    {
        $apis = [];
        if (!is_dir($srcDir)) {
            return $apis;
        }

        $files = self::rglob($srcDir, '*.php');

        foreach ($files as $file) {
            $code = (string) file_get_contents($file);
            if (preg_match('/namespace\s+([^;]+);/', $code, $nsMatch)) {
                $ns = trim($nsMatch[1]);
            } else {
                $ns = '';
            }

            if (preg_match('/class\s+([A-Za-z0-9_]+)/', $code, $clsMatch)) {
                $className = $ns ? $ns . '\\' . $clsMatch[1] : $clsMatch[1];
            } else {
                continue;
            }

            $pattern = '/((?:#\[[^\]]+\]\s*)+)public\s+function\s+([A-Za-z0-9_]+)\s*\(([^)]*)\)(?:\s*:\s*([A-Za-z0-9_\\\?]+))?/';
            if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $attrs = $m[1];
                    $methodName = $m[2];
                    $rawParams = trim($m[3]);
                    $returnType = trim($m[4] ?? '') ?: 'mixed';

                    if (preg_match('/#\[Api\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)\]/', $attrs, $apiMatch)) {
                        $ctrlIdent = $apiMatch[1]; // e.g. wsforms:form
                        $action = $apiMatch[2];    // e.g. get

                        $canPerm = null;
                        $canGroup = 'genericpermissions';
                        if (preg_match('/#\[Can\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*[\'"]([^\'"]+)[\'"])?\s*\)\]/', $attrs, $canMatch)) {
                            $canPerm = $canMatch[1];
                            $canGroup = !empty($canMatch[2]) ? $canMatch[2] : 'genericpermissions';
                        }

                        $isWrite = !in_array(strtolower($action), ['get', 'fetch', 'index', 'show', 'list', 'search', 'find'], true)
                            || str_contains($rawParams, '$securitytoken');

                        $apis[$ctrlIdent][] = [
                            'class' => $className,
                            'method' => $methodName,
                            'action' => $action,
                            'rawParams' => $rawParams,
                            'returnType' => $returnType,
                            'canPerm' => $canPerm,
                            'canGroup' => $canGroup,
                            'isWrite' => $isWrite,
                        ];
                    }
                }
            }
        }

        return $apis;
    }

    private static function generatePackageGlue(
        string $pkgDir,
        string $addonPath,
        string $id,
        array $manifest,
        array $phpHooks,
        array $apis
    ): void {
        // 1. product.php
        $productClass = $id . '_Product';
        $hooksClass = $id . '_Hooks';

        $psr4 = $manifest['autoload']['psr-4'] ?? [$id . '\\' => 'src/'];
        $psr4Code = var_export($psr4, true);

        $productCode = "<?php\n\n" .
            "declare(strict_types=1);\n\n" .
            "require_once DIR . '/packages/vblaravel/bootstrap.php';\n\n" .
            "spl_autoload_register(static function (string \$class): void {\n" .
            "    if (strncasecmp(\$class, '{$id}\\\\_Generated\\\\', strlen('{$id}\\\\_Generated\\\\')) === 0) {\n" .
            "        \$rel = substr(\$class, strlen('{$id}\\\\_Generated\\\\'));\n" .
            "        \$file = __DIR__ . '/src/_Generated/' . str_replace('\\\\', '/', \$rel) . '.php';\n" .
            "        if (is_file(\$file)) {\n" .
            "            require_once \$file;\n" .
            "            return;\n" .
            "        }\n" .
            "    }\n" .
            "    \$map = {$psr4Code};\n" .
            "    foreach (\$map as \$prefix => \$path) {\n" .
            "        if (strncasecmp(\$class, \$prefix, strlen(\$prefix)) === 0) {\n" .
            "            \$rel = substr(\$class, strlen(\$prefix));\n" .
            "            \$file = __DIR__ . '/' . rtrim(\$path, '/\\\\') . '/' . str_replace('\\\\', '/', \$rel) . '.php';\n" .
            "            if (is_file(\$file)) {\n" .
            "                require_once \$file;\n" .
            "                return;\n" .
            "            }\n" .
            "        }\n" .
            "    }\n" .
            "});\n\n" .
            "class {$productClass}\n" .
            "{\n" .
            "    public \$vbMinVersion = '6.2.2';\n" .
            "    public \$vbMaxVersion = '6.9.9';\n" .
            "    public static \$AutoInstall = false;\n" .
            "    public \$hookClasses = [\n" .
            "        '{$hooksClass}',\n" .
            "    ];\n" .
            "}\n";
        file_put_contents($pkgDir . '/product.php', $productCode);

        // 2. hooks.php
        $minOrder = 10;
        $hookMethods = '';
        foreach ($phpHooks as $hookName => $handlers) {
            foreach ($handlers as $h) {
                if ($h['order'] < $minOrder) {
                    $minOrder = $h['order'];
                }
            }
            $hookMethods .= "    public static function {$hookName}(array &\$params): void\n    {\n        \\VbLaravel\\Runtime::boot();\n        \\{$id}\\_Generated\\HookDispatcher::{$hookName}(\$params);\n    }\n\n";
        }

        $hooksCode = "<?php\n\ndeclare(strict_types=1);\n\nrequire_once __DIR__ . '/product.php';\n\nclass {$hooksClass}\n{\n    public static \$order = {$minOrder};\n\n{$hookMethods}}\n";
        file_put_contents($pkgDir . '/hooks.php', $hooksCode);

        // 3. _Generated/HookDispatcher.php in addon src/
        $genDir = $addonPath . '/src/_Generated';
        if (!is_dir($genDir)) {
            mkdir($genDir, 0777, true);
        }

        $dispatcherMethods = '';
        foreach ($phpHooks as $hookName => $handlers) {
            $calls = '';
            foreach ($handlers as $h) {
                $calls .= "        (new \\{$h['class']}())->{$h['method']}(\$params);\n";
            }
            $dispatcherMethods .= "    public static function {$hookName}(array &\$params): void\n    {\n{$calls}    }\n\n";
        }

        $dispatcherCode = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$id}\\_Generated;\n\nfinal class HookDispatcher\n{\n{$dispatcherMethods}}\n";
        file_put_contents($genDir . '/HookDispatcher.php', $dispatcherCode);

        // 4. api/<controller>.php
        $apiDir = $pkgDir . '/api';
        if (!is_dir($apiDir)) {
            mkdir($apiDir, 0777, true);
        }

        foreach ($apis as $ctrlIdent => $actions) {
            $parts = explode(':', $ctrlIdent);
            $ctrlName = end($parts);
            $apiClassName = $id . '_Api_' . ucfirst(strtolower($ctrlName));

            $apiMethods = '';
            foreach ($actions as $act) {
                $parsed = self::formatApiParameters($act['rawParams']);
                $sig = $parsed['signature'];
                $callArgs = $parsed['callArgs'];
                $ret = $act['returnType'] ? ': ' . $act['returnType'] : '';

                $apiMethods .= "    public function {$act['action']}({$sig}){$ret}\n    {\n";
                $apiMethods .= "        \\VbLaravel\\Runtime::boot();\n\n";

                if ($act['canPerm']) {
                    $pEsc = addslashes($act['canPerm']);
                    $gEsc = addslashes($act['canGroup']);
                    $apiMethods .= "        if (!vb()->can('{$pEsc}', '{$gEsc}')) {\n";
                    $apiMethods .= "            throw new \\vB_Exception_Api('no_permission');\n";
                    $apiMethods .= "        }\n\n";
                }

                if ($act['isWrite']) {
                    $apiMethods .= "        \$user = vb()->user();\n";
                    $apiMethods .= "        \$token = \$securitytoken ?? (\$_POST['securitytoken'] ?? (\$_REQUEST['securitytoken'] ?? null));\n";
                    $apiMethods .= "        if (empty(\$token) || !isset(\$user['securitytoken']) || \$token !== \$user['securitytoken']) {\n";
                    $apiMethods .= "            throw new \\vB_Exception_Api('security_token_invalid');\n";
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

    private static function formatApiParameters(string $rawParams): array
    {
        if (trim($rawParams) === '') {
            return ['signature' => '', 'callArgs' => ''];
        }

        $parts = array_map('trim', explode(',', $rawParams));
        $sigParts = [];
        $callParts = [];

        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }

            if (preg_match('/(\$([A-Za-z0-9_]+))(?:\s*=\s*(.+))?$/', $p, $m)) {
                $var = $m[1];
                $name = $m[2];
                $default = $m[3] ?? null;
                $callParts[] = $var;

                if ($default !== null) {
                    $sigParts[] = $p;
                } else {
                    $type = trim(substr($p, 0, -strlen($m[0])));
                    $defVal = match (true) {
                        str_starts_with($type, '?') => ' = null',
                        $type === 'int' => ' = 0',
                        $type === 'string' => " = ''",
                        $type === 'array' => ' = []',
                        $type === 'bool' => ' = false',
                        default => ' = null',
                    };
                    $sigParts[] = ($type ? $type . ' ' : '') . $var . $defVal;
                }
            } else {
                $sigParts[] = $p;
            }
        }

        return [
            'signature' => implode(', ', $sigParts),
            'callArgs' => implode(', ', $callParts),
        ];
    }

    private static function copyPackageAssets(string $addonPath, string $pkgDir): void
    {
        // Copy src/
        $srcDir = $addonPath . '/src';
        if (is_dir($srcDir) && realpath($srcDir) !== realpath($pkgDir . '/src')) {
            self::copyDirectory($srcDir, $pkgDir . '/src');
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

    private static function copyDirectory(string $source, string $destination): void
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
            $srcPath = $source . '/' . $file;
            $dstPath = $destination . '/' . $file;
            if (is_dir($srcPath)) {
                self::copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
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

        $installCode = htmlspecialchars(
            "\$vblBootstrap = DIR . '/packages/vblaravel/bootstrap.php';\n" .
            "if (is_file(\$vblBootstrap)) {\n" .
            "    require_once \$vblBootstrap;\n" .
            "}\n" .
            "if (class_exists('\\VbLaravel\\Migrator')) {\n" .
            "    \\VbLaravel\\Migrator::up(DIR . '/packages/{$id}');\n" .
            "}\n",
            ENT_NOQUOTES
        );

        $uninstallCode = htmlspecialchars(
            "\$vblBootstrap = DIR . '/packages/vblaravel/bootstrap.php';\n" .
            "if (is_file(\$vblBootstrap)) {\n" .
            "    require_once \$vblBootstrap;\n" .
            "}\n" .
            "if (class_exists('\\VbLaravel\\Migrator')) {\n" .
            "    \\VbLaravel\\Migrator::down(DIR . '/packages/{$id}');\n" .
            "}\n",
            ENT_NOQUOTES
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
        } else {
            $xml .= "    <templates></templates>\n";
        }
        $xml .= "    <plugins></plugins>\n";
        if ($phrasesXml) {
            $xml .= "{$phrasesXml}";
        } else {
            $xml .= "    <phrases></phrases>\n";
        }
        if ($templateHooksXml) {
            $xml .= "    <hooks>\n{$templateHooksXml}    </hooks>\n";
        } else {
            $xml .= "    <hooks></hooks>\n";
        }
        if ($settingsXml) {
            $xml .= "{$settingsXml}";
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
