<?php
/**
 * AEB (Adlaire Editor & Blocks) - PHP アセットマニフェスト
 *
 * JavaScript モジュールの PHP 側バインディングを提供する。
 * テンプレートから AEB のスクリプトを統一的に読み込むために使用。
 *
 * @since Ver.1.9
 * @license Adlaire License Ver.2.0
 */

namespace AEB\Assets;

/**
 * AEB アセットマニフェスト。
 * テンプレートエンジンから AEB の JavaScript ファイルを参照するための
 * パス解決・タグ生成を行う。
 */
class AssetManifest {

    private static readonly string BASE_PATH = 'Framework/AEB';

    /** @var array<string, string> モジュール名 → ファイル名 */
    private const MODULES = [
        'core'   => 'AEB.Core.js',
        'blocks' => 'AEB.Blocks.js',
        'utils'  => 'AEB.Utils.js',
    ];

    /**
     * 指定モジュールの相対パスを返す。
     */
    public static function path(string $module): ?string {
        $file = self::MODULES[$module] ?? null;
        return $file !== null ? self::BASE_PATH . '/' . $file : null;
    }

    /**
     * 指定モジュールの <script> タグを生成する。
     */
    public static function script(string $module, array $attributes = []): string {
        $path = self::path($module);
        if ($path === null) return '';
        $attrs = self::buildAttributes($attributes);
        return '<script type="module" src="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '></script>';
    }

    /**
     * 全モジュールの <script> タグを返す。
     */
    public static function all(array $attributes = []): string {
        $tags = [];
        foreach (array_keys(self::MODULES) as $module) {
            $tags[] = self::script($module, $attributes);
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
