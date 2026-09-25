<?php
/**
 * LLM Provider Interface
 * تمام providerهای LLM باید این interface را پیاده‌سازی کنند
 */
interface LLMProviderInterface {

    /**
     * نام provider (برای نمایش)
     */
    public function getName(): string;

    /**
     * شناسه provider (برای ذخیره در DB)
     */
    public function getSlug(): string;

    /**
     * لیست مدل‌های موجود
     */
    public function listModels(): array;

    /**
     * ارسال درخواست به LLM
     *
     * @param array $messages آرایه پیام‌ها: [['role' => 'user', 'content' => '...']]
     * @param array $options گزینه‌ها (model, temperature, ...)
     * @return array ['ok' => bool, 'response' => string, 'error' => string]
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * تست اتصال
     */
    public function testConnection(): array;

    /**
     * دانلود/نصب مدل (اختیاری)
     */
    public function pullModel(string $modelName): array;
}
