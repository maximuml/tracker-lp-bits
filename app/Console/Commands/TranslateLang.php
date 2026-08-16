<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;

/**
 * use google translate
 */
class TranslateLang extends Command
{
    /** @var  string */
    protected $signature = 'lang:translate {runEnv} {source} {target}
                            {filename? : Optional file to translate (php file or json)}
                            {--dry-run : Only print translations without writing files}
                            {--ignore= : Comma-separated keys to ignore (e.g. key1,key2)}
                            {--json : Also translate JSON language files}';

    /** @var  string */
    protected $description = 'Translate Laravel language files (PHP or JSON) using Google Translate';

    /** @var  mixed */
    protected $tr;
    /** @var  array<int|string, mixed> */
    protected $ignoreKeys = [];
    /** @var  array<int|string, mixed> */
    protected $cache = [];
    /** @var  mixed */
    protected $cachePath;
    /** @var  mixed */
    protected $langPath;

    /** @var  mixed */
    protected $runEnv;

    const RUN_ENV_NEXUS = 'nexus';
    const RUN_ENV_LARAVEL = 'laravel';

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $runEnvToLangPathMaps = [
        self::RUN_ENV_NEXUS => ROOT_PATH . "lang",
        self::RUN_ENV_LARAVEL => ROOT_PATH . "resources/lang",
    ];

    /** @return  int */
    public function handle()
    {
        $source = $this->argument('source');
        $target = $this->argument('target');
        $filename = $this->argument('filename');
        $runEnv = $this->runEnv = $this->argument('runEnv');
        $this->ignoreKeys = array_filter(explode(',', (string) $this->option('ignore')));
        $this->cachePath = storage_path("framework/lang-translate-cache.{$source}.{$target}.json");

        $this->loadCache();

        $this->tr = new GoogleTranslate();
        $supportLanguages = $this->tr->languages();
        if (!in_array($source, $supportLanguages)) {
            $this->error("source $source is not supported by Google Translate");
            return 1;
        }
        if (!in_array($target, $supportLanguages)) {
            $this->error("target $target is not supported by Google Translate");
            return 1;
        }
        $this->tr->setSource($source);
        $this->tr->setTarget($target);

        $langPath = $this->langPath = $this->getLangPath($runEnv);

        //谷歌使用的是 - ， 本地按照 ISO 15897 标准使用 _
        $source = str_replace("-", "_", $source);
        $target = str_replace("-", "_", $target);

        $realSourceDir = $source;
        if ($runEnv == self::RUN_ENV_NEXUS) {
            $realSourceDir = $this->langToDirName($source);
        }
        $sourceDir = "{$langPath}/" . $realSourceDir;
        if ($filename) {
            // 👇 指定具体文件翻译
            $this->translateSpecificFile($filename, $realSourceDir, $target);
        } else {
            // 👇 未指定时，用户确认是否翻译所有文件
            $answer = $this->ask("你没有指定文件名，是否翻译目录 $sourceDir 下所有语言文件到 $target ？请输入 yes 确认");
            if (strtolower($answer) === 'yes') {
                foreach (File::files($sourceDir) as $file) {
                    if ($file->getExtension() === 'php') {
                        $this->translatePhpFile($file->getPathname(), $source, $target);
                    }
                }
                if ($this->option('json')) {
                    $jsonFile = "{$langPath}/{$source}.json";
                    if (file_exists($jsonFile)) {
                        $this->translateJsonFile($jsonFile, $source, $target);
                    }
                }
            } else {
                // 👇 用户的输入被当作 filename 处理
                $this->translateSpecificFile($answer, $source, $target);
            }
        }

        $this->saveCache();

        $this->info("🎉 $source => $target Done !");
        return 0;
    }

    /**
     * @param  mixed  $sourceFile
     * @param  mixed  $sourceLang
     * @param  mixed  $targetLang
     * @return  mixed
     */
    protected function translatePhpFile($sourceFile, $sourceLang, $targetLang)
    {
        $relativePath = basename($sourceFile);
        $targetFile = $this->langPath . "/{$targetLang}/{$relativePath}";
        $var = '';
        if ($this->runEnv == self::RUN_ENV_LARAVEL) {
            $data = require $sourceFile;
        } else {
            require $sourceFile;
            $var = str_replace([".php", "lang_", "-"], "", $relativePath);
            $var = "lang_$var";
            $data = $$var;
        }
        $translated = $this->translateArray($data);

        $export = var_export($translated, true);
        if ($this->option('dry-run')) {
            $this->line("🔍 Would write to: $targetFile\n$export\n");
        } else {
            if (!file_exists(dirname($targetFile))) {
                mkdir(dirname($targetFile), 0755, true);
            }
            if ($this->runEnv == self::RUN_ENV_LARAVEL) {
                $contents = "<?php\n\nreturn $export;\n";
            } else {
                $contents = sprintf("<?php\n\n$%s = %s;\n", $var, $export);
            }
            file_put_contents($targetFile, $contents);
            $this->info("✅ Wrote translated file: $targetFile");
        }
    }

    /**
     * @param  mixed  $jsonFile
     * @param  mixed  $sourceLang
     * @param  mixed  $targetLang
     * @return  mixed
     */
    protected function translateJsonFile($jsonFile, $sourceLang, $targetLang)
    {
//        $targetFile = resource_path("lang/{$targetLang}.json");
        $targetFile = $this->langPath . "/{$targetLang}/{$jsonFile}";
        $raw = file_get_contents($jsonFile);
        if ($raw === false) {
            $this->error("Failed to read {$jsonFile}");
            return;
        }
        $content = json_decode($raw, true);
        $translated = [];

        foreach ($content as $key => $value) {
            if (in_array($key, $this->ignoreKeys)) {
                $translated[$key] = $value;
                continue;
            }

            $translated[$key] = $this->translateText($value);
        }

        $pretty = $this->json_encode_pretty($translated);

        if ($this->option('dry-run')) {
            $this->line("🔍 Would write to: $targetFile\n$pretty\n");
        } else {
            file_put_contents($targetFile, $pretty);
            $this->info("✅ Wrote translated JSON: $targetFile");
        }
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return  mixed
     */
    protected function translateArray(array $data)
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $this->ignoreKeys)) {
                $result[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->translateArray($value);
            } else {
                $result[$key] = $this->translateText($value);
            }
        }
        return $result;
    }

    /** @param  string  $text */
    protected function translateText(string $text): string
    {
        if (isset($this->cache[$text])) {
            $this->line("⚡️ Cached: $text => {$this->cache[$text]}");
            return $this->cache[$text];
        }

        try {
            $translated = $this->tr->translate($text);
            $this->cache[$text] = $translated;
            $this->line("🌍 $text => $translated");
            return $translated;
        } catch (\Exception $e) {
            $this->warn("❌ Failed to translate: $text");
            return $text;
        }
    }

    /**
     * @param  mixed  $filename
     * @param  mixed  $source
     * @param  mixed  $target
     * @return  mixed
     */
    protected function translateSpecificFile($filename, $source, $target)
    {
        $langPath = $this->langPath;

        if (str_ends_with($filename, '.json')) {
            $jsonPath = "{$langPath}/{$filename}";
            if (!file_exists($jsonPath)) {
                $jsonPath = "{$langPath}/{$source}.json";
            }
            if (file_exists($jsonPath)) {
                $this->translateJsonFile($jsonPath, $source, $target);
            } else {
                $this->error("❌ JSON 文件未找到：$jsonPath");
                exit();
            }
        } else {
            $phpPath = "{$langPath}/{$source}/$filename";
            if (!str_ends_with($filename, '.php')) {
                $phpPath .= '.php';
            }
            if (file_exists($phpPath)) {
                $this->translatePhpFile($phpPath, $source, $target);
            } else {
                $this->error("❌ PHP 语言文件未找到：$phpPath");
                exit();
            }
        }
    }

    /** @return  mixed */
    protected function loadCache()
    {
        if (file_exists($this->cachePath)) {
            $raw = file_get_contents($this->cachePath);
            $this->cache = $raw === false ? [] : json_decode($raw, true);
        }
    }

    /** @return  mixed */
    protected function saveCache()
    {
        if (!$this->option('dry-run')) {
            file_put_contents($this->cachePath, json_encode($this->cache, JSON_UNESCAPED_UNICODE) ?: '{}');
        }
    }

    /**
     * @param  mixed  $data
     * @param  int  $indentSize
     */
    protected function json_encode_pretty($data, int $indentSize = 4): string
    {
        // 默认格式化（PHP 默认是 2 空格）
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }

        // 自定义缩进
        $indentChar = str_repeat(' ', $indentSize);

        // 将默认的 2 空格缩进替换为自定义缩进
        $formatted = preg_replace_callback('/^( +)/m', function ($matches) use ($indentChar) {
            $level = strlen($matches[1]) / 2;
            return str_repeat($indentChar, (int)$level);
        }, $json);

        return (string) $formatted;
    }

    /** @param  mixed  $lang */
    private function langToDirName($lang): string
    {
        $map = [
            'zh_CN' => 'chs',
            'zh_TW' => 'cht',
        ];
        return $map[$lang] ?? $lang;
    }

    /** @param  mixed  $runEnv */
    private function getLangPath($runEnv): string
    {
        if (isset(self::$runEnvToLangPathMaps[$runEnv])) {
            return self::$runEnvToLangPathMaps[$runEnv];
        } else {
            throw new \InvalidArgumentException("invalid runEnv: $runEnv, allowed values: " . implode(', ', array_keys(self::$runEnvToLangPathMaps)));
        }
    }

}
