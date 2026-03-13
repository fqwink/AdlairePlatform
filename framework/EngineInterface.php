<?php
/**
 * EngineInterface - Adlaire Framework Ecosystem (AFE) エンジンインターフェース
 *
 * すべてのエンジンはこのインターフェースを実装する必要があります。
 * 最小限のメソッドのみを定義し、柔軟性を確保します。
 *
 * @package Adlaire Framework Ecosystem
 * @version 2.0.0
 * @since   2026-03-13
 */

interface EngineInterface
{
    /**
     * エンジンの初期化
     *
     * エンジンが登録された直後に呼ばれます。
     * 依存関係の解決、設定の読み込み、リソースの初期化などを行います。
     *
     * @param array $config エンジン固有の設定配列
     * @return void
     */
    public function initialize(array $config = []): void;

    /**
     * エンジンの起動
     *
     * すべてのエンジンの初期化が完了した後に呼ばれます。
     * イベントリスナーの登録、サービスの開始などを行います。
     *
     * @return void
     */
    public function boot(): void;

    /**
     * エンジンのシャットダウン
     *
     * アプリケーション終了時に呼ばれます。
     * リソースの解放、ログの書き込み、クリーンアップなどを行います。
     *
     * @return void
     */
    public function shutdown(): void;

    /**
     * エンジン名の取得
     *
     * エンジンを識別するための一意な名前を返します。
     *
     * @return string エンジン名（例: 'admin', 'api', 'template'）
     */
    public function getName(): string;

    /**
     * エンジンの優先度を取得
     *
     * 起動順序を決定するための優先度を返します。
     * 数値が小さいほど優先度が高く、先に起動されます。
     *
     * @return int 優先度（デフォルト: 100）
     */
    public function getPriority(): int;

    /**
     * 依存するエンジン名のリストを取得
     *
     * このエンジンが依存する他のエンジン名の配列を返します。
     * 依存関係は起動順序の決定に使用されます。
     *
     * @return array<string> 依存エンジン名の配列
     */
    public function getDependencies(): array;
}
