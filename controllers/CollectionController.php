<?php
/**
 * CollectionController - コレクション CRUD
 *
 * CollectionEngine の handle() private ハンドラを Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class CollectionController extends BaseController {

	/** コレクション作成 */
	public function create(Request $request): Response {
		$name  = trim($request->post('name', ''));
		$label = trim($request->post('label', ''));
		if ($name === '') {
			return $this->error('コレクション名は必須です');
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
			return $this->error('コレクション名に使用できない文字が含まれています');
		}
		if (\CollectionEngine::getCollectionDef($name) !== null) {
			return $this->error('同名のコレクションが既に存在します');
		}
		$def = ['label' => $label ?: $name, 'fields' => []];
		if (!\CollectionEngine::createCollection($name, $def)) {
			return $this->error('コレクション作成に失敗しました', 500);
		}
		\AdminEngine::logActivity('コレクション作成: ' . $name);
		if (class_exists('CacheEngine')) \CacheEngine::invalidateContent();
		return $this->ok(['name' => $name]);
	}

	/** コレクション削除 */
	public function delete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$name = trim($request->post('name', ''));
		if ($name === '' || \CollectionEngine::getCollectionDef($name) === null) {
			return $this->error('コレクションが見つかりません', 404);
		}
		if (!\CollectionEngine::deleteCollection($name)) {
			return $this->error('コレクション削除に失敗しました', 500);
		}
		\AdminEngine::logActivity('コレクション削除: ' . $name);
		if (class_exists('CacheEngine')) \CacheEngine::invalidateContent();
		return $this->ok();
	}

	/** コレクションアイテム保存 */
	public function itemSave(Request $request): Response {
		$collection = trim($request->post('collection', ''));
		$slug       = trim($request->post('slug', ''));
		$title      = trim($request->post('title', ''));
		$body       = $request->post('body', '');
		$isNew      = !empty($request->post('is_new'));
		$metaRaw    = $request->post('meta', '{}');

		if ($collection === '' || $slug === '') {
			return $this->error('コレクション名とスラッグは必須です');
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $collection) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
			return $this->error('無効なコレクション名またはスラッグ');
		}

		$meta = json_decode($metaRaw, true) ?: [];
		$itemData = array_merge($meta, ['title' => $title, 'body' => $body]);

		/* バリデーション */
		$errors = \CollectionEngine::validateFields($collection, $itemData);
		if (!empty($errors)) {
			return Response::json(['ok' => false, 'errors' => $errors], 422);
		}

		if (!\CollectionEngine::saveItem($collection, $slug, $itemData, $title, $isNew)) {
			return $this->error('アイテム保存に失敗しました', 500);
		}

		$event = $isNew ? 'item.created' : 'item.updated';
		\AdminEngine::logActivity(($isNew ? 'アイテム作成' : 'アイテム更新') . ": {$collection}/{$slug}");
		if (class_exists('WebhookEngine')) {
			\WebhookEngine::dispatch($event, ['collection' => $collection, 'slug' => $slug]);
		}
		if (class_exists('CacheEngine')) \CacheEngine::invalidateContent();

		return $this->ok(['collection' => $collection, 'slug' => $slug]);
	}

	/** コレクションアイテム削除 */
	public function itemDelete(Request $request): Response {
		$collection = trim($request->post('collection', ''));
		$slug       = trim($request->post('slug', ''));

		if ($collection === '' || $slug === '') {
			return $this->error('コレクション名とスラッグは必須です');
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $collection) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
			return $this->error('無効なパラメータ');
		}

		if (!\CollectionEngine::deleteItem($collection, $slug)) {
			return $this->error('アイテム削除に失敗しました', 500);
		}

		\AdminEngine::logActivity("アイテム削除: {$collection}/{$slug}");
		if (class_exists('WebhookEngine')) {
			\WebhookEngine::dispatch('item.deleted', ['collection' => $collection, 'slug' => $slug]);
		}
		if (class_exists('CacheEngine')) \CacheEngine::invalidateContent();

		return $this->ok();
	}

	/** pages.json → コレクションへのマイグレーション */
	public function migrate(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$result = \CollectionEngine::migrateFromPagesJson();
		\AdminEngine::logActivity('コレクションマイグレーション実行');
		return Response::json(['ok' => true, 'data' => $result]);
	}
}
