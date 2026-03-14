<?php
/**
 * CollectionEngineTest - コレクション CRUD・スキーマ管理のテスト
 */
class CollectionEngineTest extends TestCase {

	protected function setUp(): void {
		$this->clearJsonCache();
	}

	/* ═══ コレクション作成・取得 ═══ */

	public function testCreateCollectionAndList(): void {
		$result = CollectionEngine::createCollection('test_products', [
			'label' => 'テスト商品',
			'fields' => [
				['name' => 'title', 'type' => 'text', 'label' => 'タイトル'],
				['name' => 'price', 'type' => 'number', 'label' => '価格'],
			],
		]);
		$this->assertTrue($result);
		$this->clearJsonCache();
		$collections = CollectionEngine::listCollections();
		$found = false;
		foreach ($collections as $col) {
			if ($col['name'] === 'test_products') {
				$found = true;
				$this->assertEquals('テスト商品', $col['label']);
			}
		}
		$this->assertTrue($found, 'test_products should be in collection list');
	}

	public function testCreateDuplicateCollectionFails(): void {
		CollectionEngine::createCollection('dup_col', [
			'label' => 'Original',
			'fields' => [],
		]);
		$this->clearJsonCache();
		$result = CollectionEngine::createCollection('dup_col', [
			'label' => 'Duplicate',
			'fields' => [],
		]);
		$this->assertFalse($result);
	}

	public function testCreateCollectionInvalidSlug(): void {
		$this->assertFalse(CollectionEngine::createCollection('../evil', [
			'label' => 'Evil',
			'fields' => [],
		]));
	}

	public function testGetCollectionDef(): void {
		CollectionEngine::createCollection('schema_test', [
			'label' => 'スキーマテスト',
			'fields' => [
				['name' => 'name', 'type' => 'text', 'label' => '名前'],
			],
		]);
		$this->clearJsonCache();
		$def = CollectionEngine::getCollectionDef('schema_test');
		$this->assertNotNull($def);
		$this->assertEquals('スキーマテスト', $def['label'] ?? '');
		$this->assertNotEmpty($def['fields'] ?? []);
	}

	public function testGetCollectionDefNonExistent(): void {
		$def = CollectionEngine::getCollectionDef('nonexistent_xyz');
		$this->assertNull($def);
	}

	/* ═══ アイテム CRUD ═══ */

	public function testSaveAndGetItem(): void {
		$col = 'crud_test';
		CollectionEngine::createCollection($col, [
			'label' => 'CRUDテスト',
			'fields' => [
				['name' => 'title', 'type' => 'text', 'label' => 'Title'],
			],
		]);
		$this->clearJsonCache();

		$result = CollectionEngine::saveItem($col, 'item-1', ['title' => 'テストアイテム'], 'テスト本文');
		$this->assertTrue($result);

		$item = CollectionEngine::getItem($col, 'item-1');
		$this->assertNotNull($item);
		$this->assertEquals('item-1', $item['slug']);
		$this->assertEquals('テストアイテム', $item['meta']['title'] ?? '');
		$this->assertContains('テスト本文', $item['body']);
	}

	public function testGetNonExistentItem(): void {
		CollectionEngine::createCollection('empty_col', [
			'label' => '空コレクション',
			'fields' => [],
		]);
		$this->clearJsonCache();
		$item = CollectionEngine::getItem('empty_col', 'nonexistent_id_xyz');
		$this->assertNull($item);
	}

	public function testSaveItemInvalidSlug(): void {
		CollectionEngine::createCollection('slug_test', [
			'label' => 'Slug Test',
			'fields' => [],
		]);
		$this->clearJsonCache();
		/* パストラバーサル攻撃を防止 */
		$result = CollectionEngine::saveItem('slug_test', '../evil', [], 'body');
		$this->assertFalse($result);
	}

	public function testSaveItemNewModeRejectsDuplicate(): void {
		$col = 'newmode_test';
		CollectionEngine::createCollection($col, [
			'label' => 'NewMode',
			'fields' => [],
		]);
		$this->clearJsonCache();

		CollectionEngine::saveItem($col, 'existing', [], 'first');
		$result = CollectionEngine::saveItem($col, 'existing', [], 'second', true);
		$this->assertFalse($result, 'isNew=true should reject existing slug');
	}

	public function testGetItemsReturnsArray(): void {
		$col = 'items_test';
		CollectionEngine::createCollection($col, [
			'label' => 'Items',
			'fields' => [
				['name' => 'name', 'type' => 'text', 'label' => 'Name'],
			],
		]);
		$this->clearJsonCache();

		CollectionEngine::saveItem($col, 'a', ['name' => 'Item A'], 'Body A');
		CollectionEngine::saveItem($col, 'b', ['name' => 'Item B'], 'Body B');

		$items = CollectionEngine::getItems($col);
		$this->assertGreaterThan(1, count($items));
	}

	public function testDeleteItem(): void {
		$col = 'del_test';
		CollectionEngine::createCollection($col, [
			'label' => '削除テスト',
			'fields' => [],
		]);
		$this->clearJsonCache();

		CollectionEngine::saveItem($col, 'to-delete', [], 'delete me');
		$result = CollectionEngine::deleteItem($col, 'to-delete');
		$this->assertTrue($result);

		$item = CollectionEngine::getItem($col, 'to-delete');
		$this->assertNull($item);
	}

	/* ═══ コレクション削除 ═══ */

	public function testDeleteCollection(): void {
		CollectionEngine::createCollection('delete_me', [
			'label' => '削除用',
			'fields' => [],
		]);
		$this->clearJsonCache();

		$result = CollectionEngine::deleteCollection('delete_me');
		$this->assertTrue($result);

		$this->clearJsonCache();
		$def = CollectionEngine::getCollectionDef('delete_me');
		$this->assertNull($def);
	}

	public function testDeleteNonExistentCollection(): void {
		$result = CollectionEngine::deleteCollection('ghost_collection');
		$this->assertFalse($result);
	}
}
