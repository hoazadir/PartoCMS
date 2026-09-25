<?php
/**
 * STT (Speech-to-Text) Provider Interface
 */
interface STTProviderInterface {

    public function getName(): string;
    public function getSlug(): string;

    /**
     * لیست مدل‌های موجود
     */
    public function listModels(): array;

    /**
     * تبدیل صدا به متن
     *
     * @param string $audioPath مسیر فایل صوتی
     * @param array $options گزینه‌ها (model, language, ...)
     * @return array ['ok' => bool, 'text' => string, 'language' => string, 'error' => string]
     */
    public function transcribe(string $audioPath, array $options = []): array;

    /**
     * تست اتصال
     */
    public function testConnection(): array;

    /**
     * دانلود/نصب مدل
     */
    public function pullModel(string $modelName): array;
}
