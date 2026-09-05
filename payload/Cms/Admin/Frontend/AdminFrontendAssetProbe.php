<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin\Frontend;

use Pinoox\Component\Template\Frontend\FrontendConfig;

final class AdminFrontendAssetProbe
{
    private const DEFAULT_ENTRY = 'src/main.js';
    private const DEFAULT_MANIFEST = 'dist/.vite/manifest.json';
    private const MAX_MANIFEST_BYTES = 2_097_152;
    private const MAX_MANIFEST_ENTRIES = 4_096;
    private const MAX_ASSETS = 8_192;

    public function probe(string $themePath): AdminFrontendAssetStatus
    {
        $themePath = rtrim(str_replace('\\','/',$themePath),'/');

        if ($themePath === '' || !is_dir($themePath)) {
            return $this->fail(
                'missing','cms.admin.theme_missing',
                'CMS Admin theme directory is missing.',
            );
        }

        $entry = self::DEFAULT_ENTRY;
        $manifestRelative = self::DEFAULT_MANIFEST;
        $devUrl = null;

        try {
            if (class_exists(FrontendConfig::class)) {
                $config = FrontendConfig::forThemePath($themePath);
                $entries = FrontendConfig::entries($config);
                $entry = (string)($entries[0] ?? self::DEFAULT_ENTRY);
                $manifestRelative = FrontendConfig::manifestRelativePath($config, $themePath)
                    ?? self::DEFAULT_MANIFEST;
                $devUrl = FrontendConfig::resolveDevServerUrl(
                    $themePath,
                    $config,
                    $manifestRelative,
                );
            }
        } catch (\Throwable) {
            // Keep deterministic canonical defaults and continue fail-closed.
        }

        if (is_string($devUrl) && trim($devUrl) !== '') {
            return new AdminFrontendAssetStatus(
                true,
                'dev',
                'cms.admin.assets_dev',
                'CMS Admin frontend is using the Pinoox Vite development server.',
                $entry,
                $manifestRelative,
            );
        }

        if (!$this->safeRelative($manifestRelative)) {
            return $this->fail(
                'invalid','cms.admin.manifest_path_invalid',
                'CMS Admin Vite manifest path is invalid.',
                $entry,self::DEFAULT_MANIFEST,
            );
        }

        $manifestPath = $themePath . '/' . ltrim($manifestRelative,'/');
        if (!is_file($manifestPath) || is_link($manifestPath)) {
            return $this->fail(
                'missing','cms.admin.manifest_missing',
                'CMS Admin frontend assets are not built. The Vite manifest is missing.',
                $entry,$manifestRelative,
            );
        }

        $manifestSize = filesize($manifestPath);
        if (!is_int($manifestSize) || $manifestSize < 2 || $manifestSize > self::MAX_MANIFEST_BYTES) {
            return $this->fail(
                'invalid','cms.admin.manifest_size_invalid',
                'CMS Admin Vite manifest size is outside the allowed release budget.',
                $entry,$manifestRelative,
            );
        }

        $raw = file_get_contents($manifestPath);
        if (!is_string($raw)) {
            return $this->fail(
                'invalid','cms.admin.manifest_unreadable',
                'CMS Admin Vite manifest cannot be read.',
                $entry,$manifestRelative,
            );
        }

        try {
            $manifest = json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->fail(
                'invalid','cms.admin.manifest_invalid',
                'CMS Admin Vite manifest is not valid JSON.',
                $entry,$manifestRelative,
            );
        }

        if (!is_array($manifest) || count($manifest) > self::MAX_MANIFEST_ENTRIES) {
            return $this->fail(
                'invalid','cms.admin.manifest_entries_invalid',
                'CMS Admin Vite manifest entry count is invalid.',
                $entry,$manifestRelative,
            );
        }

        if (!is_array($manifest[$entry] ?? null)) {
            return $this->fail(
                'invalid','cms.admin.entry_missing',
                'CMS Admin Vite manifest does not contain the configured entry.',
                $entry,$manifestRelative,
            );
        }

        if (($manifest[$entry]['isEntry'] ?? false) !== true) {
            return $this->fail(
                'invalid','cms.admin.entry_not_entry',
                'CMS Admin Vite manifest key exists but is not marked as an entry.',
                $entry,$manifestRelative,
            );
        }

        $outDir = $this->outDirFromManifest($manifestRelative);
        if (!$this->safeRelative($outDir)) {
            return $this->fail(
                'invalid','cms.admin.out_dir_invalid',
                'CMS Admin Vite output directory is invalid.',
                $entry,$manifestRelative,
            );
        }

        $outRoot = realpath($themePath.'/'.$outDir);
        if ($outRoot === false || !is_dir($outRoot)) {
            return $this->fail(
                'missing','cms.admin.out_dir_missing',
                'CMS Admin Vite output directory is missing.',
                $entry,$manifestRelative,
            );
        }

        try {
            $assets = $this->collectAssets($manifest,$entry);
        } catch (\RuntimeException $e) {
            return $this->fail(
                'invalid',$e->getCode() > 0 ? 'cms.admin.manifest_graph_invalid' : $e->getMessage(),
                'CMS Admin Vite manifest dependency graph is invalid.',
                $entry,$manifestRelative,
            );
        }

        if ($assets === [] || count($assets) > self::MAX_ASSETS) {
            return $this->fail(
                'invalid','cms.admin.asset_count_invalid',
                'CMS Admin Vite asset count is invalid.',
                $entry,$manifestRelative,$assets,
            );
        }

        $outPrefix = rtrim(str_replace('\\','/',$outRoot),'/').'/';
        foreach ($assets as $asset) {
            if (!$this->safeRelative($asset)) {
                return $this->fail(
                    'invalid','cms.admin.asset_path_invalid',
                    'CMS Admin Vite manifest contains an unsafe asset path.',
                    $entry,$manifestRelative,$assets,
                );
            }

            $candidate = $themePath.'/'.$outDir.'/'.ltrim($asset,'/');
            $real = realpath($candidate);

            if ($real === false || !is_file($real) || is_link($candidate)) {
                return $this->fail(
                    'missing','cms.admin.asset_missing',
                    'CMS Admin manifest references an asset file that is missing.',
                    $entry,$manifestRelative,$assets,
                );
            }

            $normalized = str_replace('\\','/',$real);
            if (!str_starts_with($normalized,$outPrefix)) {
                return $this->fail(
                    'invalid','cms.admin.asset_outside_dist',
                    'CMS Admin asset resolves outside the Vite output directory.',
                    $entry,$manifestRelative,$assets,
                );
            }
        }

        $checksum = hash('sha256',$raw);
        $buildId = substr(hash('sha256',$checksum."\0".implode("\0",$assets)),0,20);

        return new AdminFrontendAssetStatus(
            true,
            'manifest',
            'cms.admin.assets_ready',
            'CMS Admin production frontend assets are available.',
            $entry,
            $manifestRelative,
            $assets,
            $checksum,
            $buildId,
        );
    }

    /**
     * @param array<string,mixed> $manifest
     * @return list<string>
     */
    private function collectAssets(array $manifest,string $entry):array
    {
        $assets=[];
        $visited=[];
        $walk=function(string $key)use(&$walk,&$assets,&$visited,$manifest):void{
            if(isset($visited[$key]))return;
            $visited[$key]=true;

            $chunk=$manifest[$key]??null;
            if(!is_array($chunk)){
                throw new \RuntimeException('cms.admin.import_missing');
            }

            $file=$chunk['file']??null;
            if(is_string($file)&&$file!=='')$assets[]=$file;

            foreach(['css','assets'] as$listKey){
                $list=$chunk[$listKey]??[];
                if($list===null)continue;
                if(!is_array($list))throw new \RuntimeException('cms.admin.asset_list_invalid');
                foreach($list as$value){
                    if(!is_string($value)||$value==='')throw new \RuntimeException('cms.admin.asset_list_invalid');
                    $assets[]=$value;
                }
            }

            foreach(['imports','dynamicImports'] as$importKey){
                $imports=$chunk[$importKey]??[];
                if($imports===null)continue;
                if(!is_array($imports))throw new \RuntimeException('cms.admin.import_list_invalid');
                foreach($imports as$dependency){
                    if(!is_string($dependency)||$dependency==='')throw new \RuntimeException('cms.admin.import_list_invalid');
                    $walk($dependency);
                }
            }
        };

        $walk($entry);
        return array_values(array_unique($assets));
    }

    private function outDirFromManifest(string $manifestRelative): string
    {
        $normalized = trim(str_replace('\\','/',$manifestRelative),'/');
        $needle = '/.vite/manifest.json';
        if (str_ends_with($normalized,$needle)) {
            return substr($normalized,0,-strlen($needle));
        }

        $dir = dirname($normalized);
        return $dir === '.' ? 'dist' : trim(str_replace('\\','/',$dir),'/');
    }

    private function safeRelative(string $path): bool
    {
        if ($path === '' || str_contains($path,"\0")) return false;
        $path = str_replace('\\','/',$path);
        if (str_starts_with($path,'/') || preg_match('/^[A-Za-z]:/',$path)) return false;
        return !in_array('..',explode('/',$path),true);
    }

    /**
     * @param list<string> $assets
     */
    private function fail(
        string $mode,
        string $code,
        string $message,
        string $entry=self::DEFAULT_ENTRY,
        string $manifest=self::DEFAULT_MANIFEST,
        array $assets=[],
    ):AdminFrontendAssetStatus{
        return new AdminFrontendAssetStatus(
            false,$mode,$code,$message,$entry,$manifest,$assets
        );
    }
}
