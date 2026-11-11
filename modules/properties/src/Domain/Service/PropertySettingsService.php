<?php
declare(strict_types=1);

namespace PartoCMS\Properties\Domain\Service;

use PDO;

/**
 * سرویس تنظیمات ماژول املاک
 * - خواندن/ذخیره تنظیمات با پیشوند properties_
 * - اعتبارسنجی مقادیر
 * - مقادیر پیش‌فرض از config.php
 * - کش درون‌درخواستی
 */
final class PropertySettingsService
{
    private const PREFIX = 'properties_';

    /** @var array<string,mixed> */
    private array $cache = [];

    /** @var array<string,mixed> */
    private array $defaults;

    /** @var array<string,array{type:string,min?:int,max?:int,allowed?:array<string>}> */
    private array $schema;

    public function __construct(private readonly PDO $pdo, array $config)
    {
        $this->defaults = array_merge($config['defaults'] ?? [], [
            'is_enabled'       => true,
            'widget_location'  => 'none',
            'widget_limit'     => 6,
            'widget_columns'   => 3,
            'widget_style'     => 'grid',
            'widget_deal_type' => '',
            'widget_featured'  => false,
            'items_per_page'   => 12,
            'require_approval' => true,
            'max_images'       => 10,
        ]);

        $this->schema = [
            'is_enabled'       => ['type' => 'bool'],
            'widget_location'  => ['type' => 'enum', 'allowed' => ['none', 'home', 'footer', 'header', 'all_pages']],
            'widget_limit'     => ['type' => 'int',  'min' => 1,   'max' => 50],
            'widget_columns'   => ['type' => 'int',  'min' => 1,   'max' => 4],
            'widget_style'     => ['type' => 'enum', 'allowed' => ['grid', 'slider', 'list']],
            'widget_deal_type' => ['type' => 'enum', 'allowed' => ['', 'sale', 'rent', 'mortgage']],
            'widget_featured'  => ['type' => 'bool'],
            'items_per_page'   => ['type' => 'int',  'min' => 1, 'max' => 100],
            'require_approval' => ['type' => 'bool'],
            'max_images'       => ['type' => 'int',  'min' => 1, 'max' => 50],
        ];
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $default = $this->defaults[$key] ?? $fallback;
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([self::PREFIX . $key]);
            $raw = $stmt->fetchColumn();
            if ($raw === false) {
                return $this->cache[$key] = $default;
            }
            return $this->cache[$key] = $this->castValue($raw, $this->schema[$key]['type'] ?? 'string');
        } catch (\PDOException) {
            return $this->cache[$key] = $default;
        }
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        $result = [];
        foreach (array_keys($this->defaults) as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function set(string $key, mixed $value): void
    {
        if (!isset($this->schema[$key])) {
            throw new \InvalidArgumentException("کلید تنظیمات نامعتبر: $key");
        }
        $normalized = $this->normalize($key, $value);
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([self::PREFIX . $key, $normalized]);
        $this->cache[$key] = $this->castValue((string) $normalized, $this->schema[$key]['type']);
    }

    /** @param array<string,mixed> $data @return array{ok:int,errors:array<string,string>} */
    public function saveMany(array $data): array
    {
        $ok = 0; $errors = [];
        foreach ($data as $key => $value) {
            if (!isset($this->schema[$key])) continue;
            try { $this->set($key, $value); $ok++; }
            catch (\InvalidArgumentException $e) { $errors[$key] = $e->getMessage(); }
        }
        return ['ok' => $ok, 'errors' => $errors];
    }

    /** @return array<string,array{type:string,min?:int,max?:int,allowed?:array<string>}> */
    public function schema(): array { return $this->schema; }

    /** @return array<string,mixed> */
    public function defaults(): array { return $this->defaults; }

    private function normalize(string $key, mixed $value): string
    {
        return match ($this->schema[$key]['type']) {
            'bool' => $value ? '1' : '0',
            'int'  => $this->validateInt($key, (int) $value),
            'enum' => $this->validateEnum($key, (string) $value),
            default => (string) $value,
        };
    }

    private function validateInt(string $key, int $value): string
    {
        $min = $this->schema[$key]['min'] ?? 0;
        $max = $this->schema[$key]['max'] ?? 999999;
        if ($value < $min || $value > $max) {
            throw new \InvalidArgumentException("مقدار $key باید بین $min و $max باشد.");
        }
        return (string) $value;
    }

    private function validateEnum(string $key, string $value): string
    {
        $allowed = $this->schema[$key]['allowed'] ?? [];
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("مقدار $key نامعتبر است.");
        }
        return $value;
    }

    private function castValue(string $raw, string $type): mixed
    {
        return match ($type) {
            'bool' => $raw === '1',
            'int'  => (int) $raw,
            default => $raw,
        };
    }
}
