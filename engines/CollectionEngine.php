<?php
/**
 * CollectionEngine - コレクション（スキーマ）管理エンジン（後方互換シム）
 *
 * Ver.1.8: 全ロジックを ACE\Core\CollectionService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — ACE\Core\CollectionService を使用してください
 */
class CollectionEngine {
	use EngineTrait;

	/** @var \ACE\Core\CollectionManager|null Ver.1.5 Framework コレクションマネージャ */
	private static ?\ACE\Core\CollectionManager $manager = null;

	/**
	 * Ver.1.5: Framework CollectionManager インスタンスを取得する
	 */
	public static function getManager(): \ACE\Core\CollectionManager {
		if (self::$manager === null) {
			self::$manager = new \ACE\Core\CollectionManager(content_dir());
		}
		return self::$manager;
	}

	/* ── コレクション有効判定 ── */

	public static function isEnabled(): bool {
		return \ACE\Core\CollectionService::isEnabled();
	}

	/* ── スキーマ管理 ── */

	public static function loadSchema(): array {
		return \ACE\Core\CollectionService::loadSchema();
	}

	public static function saveSchema(array $schema): void {
		\ACE\Core\CollectionService::saveSchema($schema);
	}

	public static function listCollections(): array {
		return \ACE\Core\CollectionService::listCollections();
	}

	public static function getCollectionDef(string $name): ?array {
		return \ACE\Core\CollectionService::getCollectionDef($name);
	}

	/* ── コレクション CRUD ── */

	public static function createCollection(string $name, array $def): bool {
		return \ACE\Core\CollectionService::createCollection($name, $def);
	}

	public static function deleteCollection(string $name): bool {
		return \ACE\Core\CollectionService::deleteCollection($name);
	}

	/* ── アイテム読み込み ── */

	public static function getItems(string $collection): array {
		return \ACE\Core\CollectionService::getItems($collection);
	}

	public static function getAllItems(string $collection): array {
		return \ACE\Core\CollectionService::getAllItems($collection);
	}

	public static function getItem(string $collection, string $slug): ?array {
		return \ACE\Core\CollectionService::getItem($collection, $slug);
	}

	/* ── アイテム書き込み ── */

	public static function saveItem(string $collection, string $slug, array $meta, string $body, bool $isNew = false): bool {
		return \ACE\Core\CollectionService::saveItem($collection, $slug, $meta, $body, $isNew);
	}

	public static function validateFields(string $collection, array $meta): array {
		return \ACE\Core\CollectionService::validateFields($collection, $meta);
	}

	public static function deleteItem(string $collection, string $slug): bool {
		return \ACE\Core\CollectionService::deleteItem($collection, $slug);
	}

	/* ── レガシー互換 ── */

	public static function loadAllAsPages(): array {
		return \ACE\Core\CollectionService::loadAllAsPages();
	}

	public static function migrateFromPagesJson(): array {
		return \ACE\Core\CollectionService::migrateFromPagesJson();
	}
}
