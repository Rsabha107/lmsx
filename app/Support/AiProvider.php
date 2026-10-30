<?php

namespace App\Support;

use App\Services\SettingsService;

/**
 * Which AI provider the app's agents use: chosen by an admin in Setups → Settings → AI provider
 * (global setting `ai.provider`), else AI_PROVIDER. Keys and endpoints stay in .env.
 */
class AiProvider
{
    public const SETTING = 'ai.provider';

    public const OPTIONS = [
        'azure' => 'Azure OpenAI (SC tenant)',
        'anthropic' => 'Anthropic Claude',
    ];

    /** The provider to use now: the admin's choice, else AI_PROVIDER, else Anthropic. */
    public static function current(): string
    {
        $chosen = app(SettingsService::class)->getGlobalValue(self::SETTING);
        if (isset(self::OPTIONS[$chosen])) {
            return $chosen;
        }

        $default = (string) config('ai.default');

        return isset(self::OPTIONS[$default]) ? $default : 'anthropic';
    }

    /** Has the provider's key (and, for Azure, its endpoint) been set in .env? */
    public static function configured(string $provider): bool
    {
        $config = config("ai.providers.{$provider}", []);

        return match ($provider) {
            'azure' => filled($config['key'] ?? null) && filled($config['url'] ?? null) && filled($config['deployment'] ?? null),
            'anthropic' => filled($config['key'] ?? null),
            default => false,
        };
    }

    /** Model for the provider: AI_MODEL for Anthropic (null = package default); Azure uses its deployment. */
    public static function model(?string $provider = null): ?string
    {
        return ($provider ?? self::current()) === 'anthropic'
            ? (config('services.anthropic.model') ?: null)
            : null;
    }

    /** Web search (the flight schedule lookup) is only offered by Anthropic here. */
    public static function webSearchProvider(): ?string
    {
        return self::configured('anthropic') ? 'anthropic' : null;
    }

    /** What the Settings screen shows: each option, whether it can be picked, and where it points. */
    public static function describe(): array
    {
        return [
            'current' => self::current(),
            'options' => collect(self::OPTIONS)->map(fn (string $label, string $key) => [
                'value' => $key,
                'label' => $label,
                'configured' => self::configured($key),
                'endpoint' => match ($key) {
                    'azure' => parse_url((string) config('ai.providers.azure.url'), PHP_URL_HOST) ?: null,
                    'anthropic' => parse_url((string) config('ai.providers.anthropic.url'), PHP_URL_HOST) ?: null,
                },
                'model' => $key === 'azure'
                    ? (config('ai.providers.azure.deployment') ?: null)
                    : (config('services.anthropic.model') ?: 'package default'),
                'env' => $key === 'azure'
                    ? 'AZURE_OPENAI_API_KEY, AZURE_OPENAI_URL, AZURE_OPENAI_DEPLOYMENT'
                    : 'ANTHROPIC_API_KEY (optional AI_MODEL)',
            ])->values()->all(),
            'webSearch' => self::webSearchProvider() !== null,
        ];
    }
}
