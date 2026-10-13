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
                $templatesXml .= "        <template name=\"{$fullTplName}\" templatetype=\"template\"><![CDATA[{$tplContent}]]></template>\n";
            }

            // Template hooks (templates/hooks/<hookname>.html)
            $tplHooksDir = $templatesDir . '/hooks';
            if (is_dir($tplHooksDir)) {
                foreach (glob($tplHooksDir . '/*.html') ?: [] as $hookTplFile) {
                    $hookName = basename($hookTplFile, '.html');
                    $hookContent = (string) file_get_contents($hookTplFile);
                    $fullHookTplName = $id . '_hook_' . $hookName;
                    $templatesXml .= "        <template name=\"{$fullHookTplName}\" templatetype=\"template\"><![CDATA[{$hookContent}]]></template>\n";
                    $templateHooksXml .= "        <hook active=\"1\" hookname=\"{$hookName}\" template=\"{$fullHookTplName}\" arguments=\"\" />\n";
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
                    $phrasesXml .= "            <phrase name=\"{$pKeyEsc}\"><![CDATA[{$pVal}]]></phrase>\n";
                }
                $phrasesXml .= "        </phrasetype>\n";
                $phrasesXml .= "    </phrases>\n";
            }
        }

        // 4. Inspect PHP Hooks and APIs in src/
        $phpHooks = self::discoverPhpHooks($addonPath . '/src');

        // 5. Build Generated Package Glue (product.php, hooks.php, HookDispatcher.php)
        self::generatePackageGlue($targetPkgDir, $addonPath, $id, $manifest, $phpHooks);

        // 6. Build product XML
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

        $files = glob($srcDir . '/*.php') ?: [];
        foreach (glob($srcDir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
            $files = array_merge($files, glob($sub . '/*.php') ?: []);
        }

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

            // Find #[Hook('name', order)]
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

    private static function generatePackageGlue(string $pkgDir, string $addonPath, string $id, array $manifest, array $phpHooks): void
    {
        // product.php
        $productClass = $id . '_Product';
        $hooksClass = $id . '_Hooks';
        $productCode = "<?php\n\ndeclare(strict_types=1);\n\nclass {$productClass}\n{\n    public \$vbMinVersion = '6.2.2';\n    public \$vbMaxVersion = '6.9.9';\n    public static \$AutoInstall = false;\n    public \$hookClasses = [\n        '{$hooksClass}',\n    ];\n}\n";
        file_put_contents($pkgDir . '/product.php', $productCode);

        // hooks.php
        $minOrder = 10;
        $hookMethods = '';
        foreach ($phpHooks as $hookName => $handlers) {
            foreach ($handlers as $h) {
                if ($h['order'] < $minOrder) {
                    $minOrder = $h['order'];
                }
            }
            $hookMethods .= "    public static function {$hookName}(array &\$params): void\n    {\n        \\{$id}\\_Generated\\HookDispatcher::{$hookName}(\$params);\n    }\n\n";
        }

        $hooksCode = "<?php\n\ndeclare(strict_types=1);\n\nclass {$hooksClass}\n{\n    public static \$order = {$minOrder};\n\n{$hookMethods}}\n";
        file_put_contents($pkgDir . '/hooks.php', $hooksCode);

        // _Generated/HookDispatcher.php in addon src/
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
        $xml .= "        <dependency dependencytype=\"product\" parentproductid=\"vblaravel\" minversion=\"0.2.0\" />\n";
        $xml .= "    </dependencies>\n";
        $xml .= "    <codes>\n";
        $xml .= "        <code version=\"{$version}\">\n";
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
}
