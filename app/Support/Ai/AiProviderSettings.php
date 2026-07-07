<?php

namespace App\Support\Ai;

use App\Support\Settings\AppSettingStore;
use Illuminate\Support\Arr;

class AiProviderSettings
{
    private const TRANSLATION_PROVIDER_KEY = 'ai.translation.provider';

    private const TRANSLATION_MODEL_KEY = 'ai.translation.model';

    private const TRANSLATION_API_KEY_KEY = 'ai.translation.api_key';

    private const TRANSLATION_FILL_LIMIT_DEFAULT_KEY = 'ai.translation.fill_limit_default';

    private const TRANSLATION_FILL_LIMIT_MAX_KEY = 'ai.translation.fill_limit_max';

    public function __construct(private readonly AppSettingStore $settingStore)
    {
        //
    }

    /**
     * @return array<int, array{value:string,label:string,default_model:string,has_config_api_key:bool,models:array<int, array{value:string,label:string}>}>
     */
    public function providerOptions(): array
    {
        return collect($this->providerConfig())
            ->map(function (array $providerConfig, string $provider): array {
                $models = collect((array) ($providerConfig['models'] ?? []))
                    ->map(function (mixed $model): array {
                        if (is_array($model)) {
                            $value = trim((string) Arr::get($model, 'value', ''));
                            $label = trim((string) Arr::get($model, 'label', $value));

                            return [
                                'value' => $value,
                                'label' => $label !== '' ? $label : $value,
                            ];
                        }

                        $value = trim((string) $model);

                        return [
                            'value' => $value,
                            'label' => $value,
                        ];
                    })
                    ->filter(static fn (array $model): bool => $model['value'] !== '')
                    ->values()
                    ->all();

                return [
                    'value' => $provider,
                    'label' => (string) ($providerConfig['label'] ?? strtoupper($provider)),
                    'default_model' => (string) ($providerConfig['default_model'] ?? ''),
                    'has_config_api_key' => $this->hasConfigApiKey($provider),
                    'models' => $models,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{provider:string,model:string,api_key:?string,has_api_key:bool,has_config_api_key:bool,has_usable_api_key:bool,fill_limit_default:int,fill_limit_max:int}
     */
    public function translationSettings(): array
    {
        $availableProviders = collect(array_keys($this->providerConfig()))
            ->filter(static fn (mixed $provider): bool => trim((string) $provider) !== '')
            ->values();

        $defaultProvider = trim((string) config('translation_editor.ai.defaults.provider', 'gemini'));

        if (! $availableProviders->contains($defaultProvider)) {
            $defaultProvider = (string) ($availableProviders->first() ?? 'gemini');
        }

        $provider = trim((string) $this->settingStore->get(self::TRANSLATION_PROVIDER_KEY, $defaultProvider));

        if (! $availableProviders->contains($provider)) {
            $provider = $defaultProvider;
        }

        $defaultModel = $this->defaultModelForProvider($provider);
        $model = trim((string) $this->settingStore->get(self::TRANSLATION_MODEL_KEY, $defaultModel));

        if ($model === '') {
            $model = $defaultModel;
        }

        $apiKey = $this->settingStore->get(self::TRANSLATION_API_KEY_KEY);
        $configuredMax = (int) config('translation_editor.ai.fill_limit_max', 500);

        if ($configuredMax <= 0) {
            $configuredMax = 500;
        }

        $configuredDefault = (int) config('translation_editor.ai.fill_limit_default', 100);

        if ($configuredDefault <= 0) {
            $configuredDefault = min(100, $configuredMax);
        }

        $fillLimitMax = (int) $this->settingStore->get(
            self::TRANSLATION_FILL_LIMIT_MAX_KEY,
            (string) $configuredMax,
        );

        if ($fillLimitMax <= 0) {
            $fillLimitMax = $configuredMax;
        }

        $fillLimitDefault = (int) $this->settingStore->get(
            self::TRANSLATION_FILL_LIMIT_DEFAULT_KEY,
            (string) $configuredDefault,
        );

        if ($fillLimitDefault <= 0) {
            $fillLimitDefault = min($configuredDefault, $fillLimitMax);
        }

        if ($fillLimitDefault > $fillLimitMax) {
            $fillLimitDefault = $fillLimitMax;
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
            'has_api_key' => trim((string) $apiKey) !== '',
            'has_config_api_key' => $this->hasConfigApiKey($provider),
            'has_usable_api_key' => trim((string) $apiKey) !== '' || $this->hasConfigApiKey($provider),
            'fill_limit_default' => $fillLimitDefault,
            'fill_limit_max' => $fillLimitMax,
        ];
    }

    public function saveTranslationSettings(
        string $provider,
        string $model,
        ?string $apiKey,
        int $fillLimitDefault,
        int $fillLimitMax,
    ): void {
        $this->settingStore->put(self::TRANSLATION_PROVIDER_KEY, $provider);
        $this->settingStore->put(self::TRANSLATION_MODEL_KEY, $model);
        $this->settingStore->put(self::TRANSLATION_API_KEY_KEY, $apiKey, encrypted: true);
        $this->settingStore->put(self::TRANSLATION_FILL_LIMIT_DEFAULT_KEY, (string) $fillLimitDefault);
        $this->settingStore->put(self::TRANSLATION_FILL_LIMIT_MAX_KEY, (string) $fillLimitMax);
    }

    private function defaultModelForProvider(string $provider): string
    {
        $configuredDefault = trim((string) data_get($this->providerConfig(), $provider.'.default_model', ''));

        if ($configuredDefault !== '') {
            return $configuredDefault;
        }

        $defaultModel = trim((string) config('translation_editor.ai.defaults.model', ''));

        if ($defaultModel !== '') {
            return $defaultModel;
        }

        return 'gemini-2.5-flash';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function providerConfig(): array
    {
        $config = config('translation_editor.ai.providers', []);

        return is_array($config) ? $config : [];
    }

    private function hasConfigApiKey(string $provider): bool
    {
        return trim((string) config('ai.providers.'.$provider.'.key', '')) !== '';
    }
}
