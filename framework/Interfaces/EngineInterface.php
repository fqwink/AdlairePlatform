<?php
/**
 * framework/Interfaces/EngineInterface.php - エンジンインターフェース
 * 
 * 全エンジンが実装すべきインターフェース。
 * フレームワークはこのインターフェースを通じてエンジンのライフサイクルを管理する。
 * 
 * @package AdlairePlatform\Framework\Interfaces
 * @version 2.0.0-alpha.1
 */

namespace AdlairePlatform\Framework\Interfaces;

interface EngineInterface {
    
    /**
     * エンジン名を取得（一意の識別子）
     * 
     * 例: 'admin', 'api', 'static', 'collection'
     * 
     * 命名規則:
     * - 小文字のみ
     * - ハイフン区切り可（例: 'custom-search'）
     * - 15文字以内推奨
     * 
     * @return string エンジン名
     */
    public function getName(): string;
    
    /**
     * エンジンバージョンを取得
     * 
     * セマンティックバージョニング（Major.Minor.Patch）形式を推奨。
     * 例: '1.0.0', '2.1.3', '1.0.0-beta.1'
     * 
     * @return string バージョン文字列
     */
    public function getVersion(): string;
    
    /**
     * 依存エンジン名の配列を取得
     * 
     * このエンジンが依存する他のエンジン名をリストアップ。
     * フレームワークは依存関係を解決して起動順序を決定する。
     * 
     * 例: ['logger', 'cache', 'database']
     * 
     * 注意:
     * - 循環依存は検出されて例外がスローされる
     * - 存在しないエンジンを指定しても起動は継続される
     * 
     * @return array<string> 依存エンジン名の配列
     */
    public function getDependencies(): array;
    
    /**
     * エンジン登録フェーズ（Phase 1）
     * 
     * フレームワークの起動時に全エンジンの register() が順次呼ばれる。
     * このフェーズでは、他のエンジンはまだ起動していないため、
     * 依存関係を持たない登録処理のみ行うこと。
     * 
     * 推奨される処理:
     * - DIコンテナへのサービスバインディング
     * - ルート定義の登録
     * - イベントリスナーの登録
     * - 設定ファイルの読み込み
     * 
     * 避けるべき処理:
     * - 他エンジンのメソッド呼び出し
     * - データベースアクセス
     * - 外部APIコール
     * - 重い初期化処理
     * 
     * @return void
     */
    public function register(): void;
    
    /**
     * エンジン起動フェーズ（Phase 2）
     * 
     * 全エンジンの register() 完了後、依存関係順に boot() が呼ばれる。
     * このフェーズでは、依存エンジンは既に起動しているため、
     * それらを利用した初期化処理を行うことができる。
     * 
     * 推奨される処理:
     * - データの読み込み（DB、ファイル等）
     * - キャッシュの初期化
     * - 依存エンジンを使った初期化
     * - 重い計算処理
     * - イベントのサブスクライブ
     * 
     * 注意:
     * - boot() は一度だけ呼ばれる
     * - 例外をスローするとアプリケーション起動が中断される
     * 
     * @return void
     */
    public function boot(): void;
    
    /**
     * エンジンシャットダウンフェーズ（Phase 3）
     * 
     * アプリケーション終了時に、起動順の逆順で shutdown() が呼ばれる。
     * リソースの解放やクリーンアップ処理を行う。
     * 
     * 推奨される処理:
     * - データの保存（バッファのフラッシュ等）
     * - ファイルハンドルのクローズ
     * - データベース接続のクローズ
     * - ログの最終出力
     * - 一時ファイルの削除
     * 
     * 注意:
     * - このフェーズでは他のエンジンが既にシャットダウンしている可能性がある
     * - 例外をスローしても無視される（ログには記録される）
     * - 依存エンジンへのアクセスは避けること
     * 
     * @return void
     */
    public function shutdown(): void;
}
