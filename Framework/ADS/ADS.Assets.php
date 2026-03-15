<?php
/**
 * ADS (Adlaire Design System) - PHP アセットマニフェスト
 *
 * CSS デザインシステムの PHP 側バインディングを提供する。
 * テンプレートから ADS のスタイルシートを統一的に読み込むために使用。
 *
 * @since Ver.1.9
 * @license Adlaire License Ver.2.0
 */

namespace ADS\Assets;

/**
 * ADS アセットマニフェスト。
 * テンプレートエンジンから ADS の CSS ファイルを参照するための
 * パス解決・タグ生成を行う。
 */
class AssetManifest {

    private static readonly string BASE_PATH = 'Framework/ADS';

    /** @var array<string, string> モジュール名 → ファイル名 */
    private const MODULES = [
        'base'       => 'ADS.Base.css',
        'components' => 'ADS.Components.css',
        'editor'     => 'ADS.Editor.css',
    ];

    /**
     * 指定モジュールの相対パスを返す。
     */
    public static function path(string $module): ?string {
        $file = self::MODULES[$module] ?? null;
        return $file !== null ? self::BASE_PATH . '/' . $file : null;
    }

    /**
     * 指定モジュールの <link> タグを生成する。
     */
    public static function stylesheet(string $module, array $attributes = []): string {
        $path = self::path($module);
        if ($path === null) return '';
        $attrs = self::buildAttributes($attributes);
        return '<link rel="stylesheet" href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '>';
    }

    /**
     * 全モジュールの <link> タグを返す。
     */
    public static function all(array $attributes = []): string {
        $tags = [];
        foreach (array_keys(self::MODULES) as $module) {
            $tags[] = self::stylesheet($module, $attributes);
        }
        return implode("\n", $tags);
    }

    /**
     * 登録済みモジュール一覧を返す。
     */
    public static function modules(): array {
        return array_keys(self::MODULES);
    }

    private static function buildAttributes(array $attributes): string {
        if (empty($attributes)) return '';
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return ' ' . implode(' ', $parts);
    }
}
