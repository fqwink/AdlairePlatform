<?php
/**
 * AdminController - 管理アクション（フィールド保存・画像・ページ削除・リビジョン・ユーザー・リダイレクト）
 *
 * AdminEngine::handle() の private ハンドラを Controller メソッドとして提供。
 * ビジネスロジックは既存エンジンの public メソッドに委譲する。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class AdminController extends BaseController {

	/* ═══ フィールド保存 ═══ */

	public function editField(Request $request): Response {
		if ($err = $this->requireRole('editor')) return $err;

		$fieldname = $request->post('fieldname', '');
		$content   = trim($request->post('content', ''));
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			return $this->error(\I18n::t('auth.invalid_field_name'));
		}

		$settingsKeys = ['title', 'description', 'keywords', 'copyright', 'themeSelect', 'menu', 'subside', 'contact_email'];
		if (in_array($fieldname, $settingsKeys, true)) {
			$settings = json_read('settings.json', settings_dir());
			$settings[$fieldname] = $content;
			json_write('settings.json', $settings, settings_dir());
			\AdminEngine::logActivity('設定変更: ' . $fieldname);
		} else {
			\AdminEngine::saveRevision($fieldname, $content);
			$pages = json_read('pages.json', content_dir());
			$pages[$fieldname] = $content;
			json_write('pages.json', $pages, content_dir());
			\AdminEngine::logActivity('ページ編集: ' . $fieldname);
			if (class_exists('WebhookEngine')) {
				\WebhookEngine::dispatch('page.updated', ['slug' => $fieldname]);
			}
		}
		if (class_exists('CacheEngine')) \CacheEngine::invalidateContent();

		return Response::json(
			['ok' => true, 'content' => $content],
			200,
			['Content-Type' => 'application/json; charset=UTF-8']
		);
	}

	/* ═══ 画像アップロード ═══ */

	public function uploadImage(Request $request): Response {
		if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
			return $this->error('ファイルエラー');
		}
		$file = $_FILES['image'];
		if ($file['size'] > 2 * 1024 * 1024) {
			return $this->error('ファイルサイズが上限（2MB）を超えています');
		}
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->file($file['tmp_name']);
		$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
		if (!isset($extMap[$mime])) {
			return $this->error('許可されていないファイル形式です');
		}
		$dir = 'uploads/';
		if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
			return $this->error('アップロードディレクトリを作成できません', 500);
		}
		$filename = bin2hex(random_bytes(12)) . '.' . $extMap[$mime];
		if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
			return $this->error('ファイル保存に失敗しました', 500);
		}
		if (class_exists('ImageOptimizer')) \ImageOptimizer::optimize($dir . $filename);
		\AdminEngine::logActivity('画像アップロード: ' . $filename);

		return Response::json(['url' => $dir . $filename]);
	}

	/* ═══ ページ削除 ═══ */

	public function deletePage(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$slug = $request->post('slug', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
			return $this->error('不正なページ名');
		}
		$pages = json_read('pages.json', content_dir());
		if (!isset($pages[$slug])) {
			return $this->error('ページが見つかりません', 404);
		}
		\AdminEngine::saveRevision($slug, $pages[$slug]);
		unset($pages[$slug]);
		json_write('pages.json', $pages, content_dir());
		\AdminEngine::logActivity('ページ削除: ' . $slug);

		return $this->ok();
	}

	/* ═══ リビジョン管理 ═══ */

	public function listRevisions(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			return $this->error('Invalid fieldname');
		}
		$offset = max(0, (int)$request->post('offset', 0));
		$limit  = min(50, max(1, (int)$request->post('limit', 20)));

		$dir = content_dir() . '/revisions/' . $fieldname . '/';
		$files = glob($dir . 'rev_*.json') ?: [];
		rsort($files);
		$total = count($files);
		$files = array_slice($files, $offset, $limit);

		$revisions = [];
		foreach ($files as $f) {
			$data = \FileSystem::readJson($f);
			if ($data === null) continue;
			$revisions[] = [
				'file'      => basename($f, '.json'),
				'timestamp' => $data['timestamp'] ?? '',
				'size'      => $data['size'] ?? 0,
				'user'      => $data['user'] ?? '',
				'restored'  => !empty($data['restored']),
				'pinned'    => !empty($data['pinned']),
			];
		}
		return Response::json(['revisions' => $revisions, 'total' => $total, 'offset' => $offset, 'limit' => $limit]);
	}

	public function getRevision(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$revFile   = $request->post('revision', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9a-f_]+$/', $revFile)) {
			return $this->error('Invalid parameters');
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) return $this->error('Revision not found', 404);
		$rev = \FileSystem::readJson($path);
		if ($rev === null || !isset($rev['content'])) return $this->error('Invalid revision data', 500);

		return Response::json(['ok' => true, 'content' => $rev['content']]);
	}

	public function restoreRevision(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$revFile   = $request->post('revision', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9a-f_]+$/', $revFile)) {
			return $this->error('Invalid parameters');
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) return $this->error('Revision not found', 404);
		$rev = \FileSystem::readJson($path);
		if ($rev === null || !isset($rev['content'])) return $this->error('Invalid revision data', 500);

		$content = $rev['content'];
		\AdminEngine::saveRevision($fieldname, $content, true);
		$pages = json_read('pages.json', content_dir());
		$pages[$fieldname] = $content;
		json_write('pages.json', $pages, content_dir());
		\AdminEngine::logActivity('リビジョン復元: ' . $fieldname);

		return Response::json(['ok' => true, 'content' => $content]);
	}

	public function pinRevision(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$revFile   = $request->post('revision', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9a-f_]+$/', $revFile)) {
			return $this->error('Invalid parameters');
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) return $this->error('Revision not found', 404);
		$rev = \FileSystem::readJson($path);
		if ($rev === null) return $this->error('Invalid revision data', 500);

		$rev['pinned'] = empty($rev['pinned']);
		\FileSystem::writeJson($path, $rev);

		return Response::json(['ok' => true, 'pinned' => $rev['pinned']]);
	}

	public function searchRevisions(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$query     = $request->post('query', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			return $this->error('Invalid fieldname');
		}
		$dir = content_dir() . '/revisions/' . $fieldname . '/';
		$files = glob($dir . 'rev_*.json') ?: [];
		rsort($files);
		$results = [];
		foreach ($files as $f) {
			$data = \FileSystem::readJson($f);
			if ($data === null) continue;
			if ($query !== '' && stripos($data['content'] ?? '', $query) === false) continue;
			$results[] = [
				'file'      => basename($f, '.json'),
				'timestamp' => $data['timestamp'] ?? '',
				'size'      => $data['size'] ?? 0,
				'user'      => $data['user'] ?? '',
				'restored'  => !empty($data['restored']),
				'pinned'    => !empty($data['pinned']),
			];
		}
		return Response::json(['revisions' => $results]);
	}

	/* ═══ ユーザー管理 ═══ */

	public function userAdd(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$username = trim($request->post('username', ''));
		$password = $request->post('password', '');
		$role = trim($request->post('role', 'editor'));
		if ($username === '' || $password === '') {
			return $this->error('ユーザー名とパスワードは必須です');
		}
		if (!\AdminEngine::addUser($username, $password, $role)) {
			return $this->error('ユーザーの追加に失敗しました');
		}
		\AdminEngine::logActivity("ユーザー追加: {$username} ({$role})");
		return Response::json(['ok' => true, 'data' => ['username' => $username, 'role' => $role]]);
	}

	public function userDelete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$username = trim($request->post('username', ''));
		if ($username === \AdminEngine::currentUsername()) {
			return $this->error('自分自身は削除できません');
		}
		if (!\AdminEngine::deleteUser($username)) {
			return $this->error('ユーザーの削除に失敗しました');
		}
		\AdminEngine::logActivity("ユーザー削除: {$username}");
		return Response::json(['ok' => true, 'data' => ['deleted' => $username]]);
	}

	/* ═══ リダイレクト管理 ═══ */

	public function redirectAdd(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$from = trim($request->post('from', ''));
		$to   = trim($request->post('to', ''));
		$code = (int)$request->post('code', 301);
		if ($from === '' || $to === '') {
			return $this->error('旧URLと新URLは必須です');
		}
		$from = preg_replace('/[\r\n\x00-\x1f]/', '', $from);
		$to   = preg_replace('/[\r\n\x00-\x1f]/', '', $to);
		if (!str_starts_with($from, '/')) {
			return $this->error('旧URLは / で始まる必要があります');
		}
		if (!str_starts_with($to, '/') && !preg_match('#^https?://#i', $to)) {
			return $this->error('リダイレクト先は / で始まるパスまたは https:// URL を指定してください');
		}
		if (!in_array($code, [301, 302], true)) $code = 301;
		$redirects = json_read('redirects.json', settings_dir());
		foreach ($redirects as $r) {
			if (($r['from'] ?? '') === $from) {
				return $this->error('この旧URLのリダイレクトは既に存在します');
			}
		}
		$redirects[] = ['from' => $from, 'to' => $to, 'code' => $code];
		json_write('redirects.json', $redirects, settings_dir());
		\AdminEngine::logActivity("リダイレクト追加: {$from} → {$to}");
		return $this->ok();
	}

	public function redirectDelete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$index = (int)$request->post('index', -1);
		$redirects = json_read('redirects.json', settings_dir());
		if ($index < 0 || $index >= count($redirects)) {
			return $this->error('無効なインデックス');
		}
		array_splice($redirects, $index, 1);
		json_write('redirects.json', $redirects, settings_dir());
		\AdminEngine::logActivity("リダイレクト削除: #{$index}");
		return $this->ok();
	}
}
