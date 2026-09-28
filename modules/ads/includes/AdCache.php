<?php
/**
 * PartoCMS - Ads Module - AdCache
 * کش سبک فایل‌محور برای تبلیغات
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class AdCache
{
    private string $cacheDir;
    private int $defaultTtl;
    private bool $enabled;

    public function __construct(?string $cacheDir = null, int $defaultTtl = 300, bool $enabled = true)
    {
        $this->cacheDir = $cacheDir ?? __DIR__ . '/../../../logs/ads_cache/';
        $this->defaultTtl = $defaultTtl;
        $this->enabled = $enabled;

        if ($this->enabled && !is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * خواندن از کش
     */
    public function get(string $key): ?array
    {
        if (!$this->enabled) return null;

        $file = $this->getFilePath($key);
        if (!file_exists($file)) return null;

        // بررسی انقضا
        $data = @json_decode(file_get_contents($file), true);
        if (!is_array($data) || !isset($data['_expires'])) {
            @unlink($file);
            return null;
        }

        if ($data['_expires'] < time()) {
            @unlink($file);
            return null;
        }

        unset($data['_expires']);
        return $data['value'] ?? null;
    }

    /**
     * ذخیره در کش
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->enabled) return false;

        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getFilePath($key);

        $data = [
            '_expires' => time() + $ttl,
            'value'    => $value,
        ];

        return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * حذف یک کلید
     */
    public function forget(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    /**
     * پاک کردن همه کش
     */
    public function flush(): int
    {
        if (!is_dir($this->cacheDir)) return 0;

        $count = 0;
        foreach (glob($this->cacheDir . '*.json') as $file) {
            if (@unlink($file)) $count++;
        }
        return $count;
    }

    /**
     * حذف کش‌های منقضی
     */
    public function gc(): int
    {
        if (!is_dir($this->cacheDir)) return 0;

        $count = 0;
        foreach (glob($this->cacheDir . '*.json') as $file) {
            $data = @json_decode(file_get_contents($file), true);
            if (!is_array($data) || ($data['_expires'] ?? 0) < time()) {
                if (@unlink($file)) $count++;
            }
        }
        return $count;
    }

    /**
     * آمار کش
     */
    public function stats(): array
    {
        if (!is_dir($this->cacheDir)) {
            return ['files' => 0, 'size' => 0];
        }

        $files = glob($this->cacheDir . '*.json');
        $size = 0;
        foreach ($files as $f) {
            $size += filesize($f);
        }

        return [
            'files' => count($files),
            'size'  => $size,
        ];
    }

    /**
     * فعال/غیرفعال کردن
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * مسیر فایل کش
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.json';
    }
}
