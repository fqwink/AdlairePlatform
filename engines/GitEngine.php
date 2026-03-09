<?php
/**
 * GitEngine - Git/GitHub 連携エンジン
 *
 * pitcms.net の核心機能に相当。
 * コンテンツを GitHub リポジトリと双方向同期する。
 *
 * 動作モード:
 *   1. git コマンド実行モード（サーバーに Git がある場合）
 *   2. GitHub REST API モード（Git がない共用サーバー用）
 *   → 自動検出で切り替え
 *
 * 設定ファイル: data/settings/git_config.json
 */
class GitEngine {

	private const CONFIG_FILE    = 'git_config.json';
	/* M23 fix: スラッシュとドットを除外（パストラバーサル防止） */
	private const SLUG_PATTERN   = '/^[a-zA-Z0-9_\-]+$/';
	private const API_BASE       = 'https://api.github.com';
	private const COMMIT_AUTHOR  = 'AdlairePlatform';
	private const COMMIT_EMAIL   = 'ap@localhost';
	private const MAX_FILE_SIZE  = 50 * 1024 * 1024; /* 50MB */
	private const MAX_RETRIES    = 3;

	/* ══════════════════════════════════════════════
	   設定管理
	   ══════════════════════════════════════════════ */

	/** Git 連携が有効か */
	public static function isEnabled(): bool {
		$cfg = self::loadConfig();
		return !empty($cfg['enabled'])
			&& !empty($cfg['repository'])
			&& !empty($cfg['token']);
	}

	/** 設定を読み込み */
	public static function loadConfig(): array {
		return json_read(self::CONFIG_FILE, settings_dir());
	}

	/** 設定を保存 */
	public static function saveConfig(array $config): void {
		json_write(self::CONFIG_FILE, $config, settings_dir());
	}

	/** Git コマンドが利用可能か */
	public static function hasGitCommand(): bool {
		$result = @exec('which git 2>/dev/null', $output, $code);
		return $code === 0 && !empty($result);
	}

	/* ══════════════════════════════════════════════
	   GitHub API ヘルパー
	   ══════════════════════════════════════════════ */

	/**
	 * GitHub REST API を呼び出し（指数バックオフ付きリトライ対応）
	 */
	private static function apiRequest(
		string $method,
		string $endpoint,
		?array $body = null
	): array {
		$cfg = self::loadConfig();
		$token = $cfg['token'] ?? '';
		$url = self::API_BASE . $endpoint;

		$headers = [
			'Authorization: Bearer ' . $token,
			'Accept: application/vnd.github.v3+json',
			'User-Agent: AdlairePlatform/' . (defined('AP_VERSION') ? AP_VERSION : '1.0'),
		];

		if ($body !== null) {
			$headers[] = 'Content-Type: application/json';
		}

		for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_CUSTOMREQUEST  => $method,
			]);

			if ($body !== null) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
			}

			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$error = curl_error($ch);
			curl_close($ch);

			/* cURL エラーまたはサーバーエラー/レート制限 → リトライ */
			$shouldRetry = ($error !== '')
				|| $httpCode === 429
				|| $httpCode === 502
				|| $httpCode === 503;

			if ($shouldRetry && $attempt < self::MAX_RETRIES) {
				usleep((int)(pow(2, $attempt) * 500000)); /* 0.5s, 1s, 2s */
				continue;
			}

			if ($error) {
				return ['ok' => false, 'error' => 'cURL エラー: ' . $error, 'status' => 0];
			}

			$data = json_decode($response, true);
			if (!is_array($data)) $data = [];

			return [
				'ok'     => $httpCode >= 200 && $httpCode < 300,
				'status' => $httpCode,
				'data'   => $data,
			];
		}

		return ['ok' => false, 'error' => 'リトライ上限に達しました', 'status' => 0];
	}

	/* ══════════════════════════════════════════════
	   接続テスト
	   ══════════════════════════════════════════════ */

	/** リポジトリへの接続をテスト */
	public static function testConnection(): array {
		$cfg = self::loadConfig();
		if (empty($cfg['repository']) || empty($cfg['token'])) {
			return ['ok' => false, 'error' => 'リポジトリまたはトークンが未設定です'];
		}

		$repo = $cfg['repository'];
		$res = self::apiRequest('GET', "/repos/{$repo}");

		if (!$res['ok']) {
			$msg = $res['data']['message'] ?? '接続に失敗しました';
			return ['ok' => false, 'error' => $msg . ' (HTTP ' . $res['status'] . ')'];
		}

		return [
			'ok'          => true,
			'name'        => $res['data']['full_name'] ?? $repo,
			'private'     => $res['data']['private'] ?? false,
			'default_branch' => $res['data']['default_branch'] ?? 'main',
		];
	}

	/* ══════════════════════════════════════════════
	   コンテンツ取得（Pull）
	   ══════════════════════════════════════════════ */

	/**
	 * リポジトリからコンテンツを取得（Pull 相当）。
	 * content/ ディレクトリの Markdown ファイルをダウンロードして同期。
	 */
	public static function pull(): array {
		$cfg = self::loadConfig();
		if (!self::isEnabled()) {
			return ['ok' => false, 'error' => 'Git 連携が無効です'];
		}

		$repo = $cfg['repository'];
		$branch = $cfg['branch'] ?? 'main';
		$remoteDir = $cfg['content_dir'] ?? 'content';

		/* リポジトリのツリーを取得 */
		$res = self::apiRequest('GET', "/repos/{$repo}/git/trees/{$branch}?recursive=1");
		if (!$res['ok']) {
			return ['ok' => false, 'error' => 'ツリーの取得に失敗: ' . ($res['data']['message'] ?? '')];
		}

		$tree = $res['data']['tree'] ?? [];
		$downloaded = 0;
		$skipped = 0;
		$errors = [];

		foreach ($tree as $item) {
			if ($item['type'] !== 'blob') continue;
			$path = $item['path'];

			/* content/ ディレクトリ内の .md ファイルのみ対象 */
			if (!str_starts_with($path, $remoteDir . '/')) continue;
			if (!str_ends_with($path, '.md') && $path !== $remoteDir . '/ap-collections.json') continue;

			/* ローカルパスを計算 */
			$relativePath = substr($path, strlen($remoteDir) + 1);
			$localPath = content_dir() . '/' . $relativePath;

			/* ディレクトリ作成 */
			$localDir = dirname($localPath);
			if (!is_dir($localDir)) mkdir($localDir, 0755, true);

			/* ファイルサイズチェック */
			$fileSize = $item['size'] ?? 0;
			if ($fileSize > self::MAX_FILE_SIZE) {
				$errors[] = "サイズ超過（{$fileSize} bytes）: {$path}";
				continue;
			}

			/* ファイル内容を取得 */
			$fileRes = self::apiRequest('GET', "/repos/{$repo}/contents/{$path}?ref={$branch}");
			if (!$fileRes['ok']) {
				$errors[] = "取得失敗: {$path}";
				continue;
			}

			$content = $fileRes['data']['content'] ?? '';
			$encoding = $fileRes['data']['encoding'] ?? '';

			if ($encoding === 'base64') {
				$decoded = base64_decode($content);
			} else {
				$decoded = $content;
			}

			if ($decoded === false) {
				$errors[] = "デコード失敗: {$path}";
				continue;
			}

			/* SHA 比較でスキップ判定 */
			$remoteSha = $item['sha'] ?? '';
			if (file_exists($localPath)) {
				$localContent = file_get_contents($localPath);
				$localSha = sha1('blob ' . strlen($localContent) . "\0" . $localContent);
				if ($localSha === $remoteSha) {
					$skipped++;
					continue;
				}
			}

			file_put_contents($localPath, $decoded, LOCK_EX);
			$downloaded++;
		}

		/* 最終同期時刻を記録 */
		$cfg['last_sync'] = date('c');
		$cfg['last_sync_direction'] = 'pull';
		self::saveConfig($cfg);

		$result = [
			'ok'         => true,
			'downloaded' => $downloaded,
			'skipped'    => $skipped,
		];
		if ($errors) $result['errors'] = $errors;

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("Git Pull: {$downloaded}件ダウンロード, {$skipped}件スキップ");
		}

		return $result;
	}

	/* ══════════════════════════════════════════════
	   コンテンツ送信（Push）
	   ══════════════════════════════════════════════ */

	/**
	 * ローカルコンテンツをリポジトリに送信（Push 相当）。
	 * content/ ディレクトリの Markdown ファイルをアップロード。
	 */
	public static function push(string $commitMessage = ''): array {
		$cfg = self::loadConfig();
		if (!self::isEnabled()) {
			return ['ok' => false, 'error' => 'Git 連携が無効です'];
		}

		$repo = $cfg['repository'];
		$branch = $cfg['branch'] ?? 'main';
		$remoteDir = $cfg['content_dir'] ?? 'content';
		if ($commitMessage === '') {
			$commitMessage = 'Update content from AdlairePlatform';
		}

		/* 1. 現在の HEAD コミット SHA を取得 */
		$refRes = self::apiRequest('GET', "/repos/{$repo}/git/ref/heads/{$branch}");
		if (!$refRes['ok']) {
			return ['ok' => false, 'error' => 'ブランチ参照の取得に失敗: ' . ($refRes['data']['message'] ?? '')];
		}
		$headSha = $refRes['data']['object']['sha'] ?? '';
		if ($headSha === '') {
			return ['ok' => false, 'error' => 'HEAD SHA を取得できません'];
		}

		/* 2. 現在のツリー SHA を取得 */
		$commitRes = self::apiRequest('GET', "/repos/{$repo}/git/commits/{$headSha}");
		if (!$commitRes['ok']) {
			return ['ok' => false, 'error' => 'コミット情報の取得に失敗'];
		}
		$baseTreeSha = $commitRes['data']['tree']['sha'] ?? '';

		/* 3. ローカルの content/ ファイルを収集 */
		$files = self::collectLocalFiles(content_dir());
		if (empty($files)) {
			return ['ok' => false, 'error' => 'アップロードするファイルがありません'];
		}

		/* 4. Blob を作成してツリーエントリを構築 */
		$treeEntries = [];
		$uploaded = 0;
		$errors = [];

		foreach ($files as $relativePath => $localPath) {
			$content = file_get_contents($localPath);
			if ($content === false) {
				$errors[] = "読み込み失敗: {$localPath}";
				continue;
			}

			$remotePath = $remoteDir . '/' . $relativePath;

			/* Blob 作成 */
			$blobRes = self::apiRequest('POST', "/repos/{$repo}/git/blobs", [
				'content'  => base64_encode($content),
				'encoding' => 'base64',
			]);

			if (!$blobRes['ok']) {
				$errors[] = "Blob 作成失敗: {$remotePath}";
				continue;
			}

			$treeEntries[] = [
				'path' => $remotePath,
				'mode' => '100644',
				'type' => 'blob',
				'sha'  => $blobRes['data']['sha'],
			];
			$uploaded++;
		}

		if (empty($treeEntries)) {
			return ['ok' => false, 'error' => 'アップロード可能なファイルがありません', 'errors' => $errors];
		}

		/* 5. 新しいツリーを作成 */
		$treeRes = self::apiRequest('POST', "/repos/{$repo}/git/trees", [
			'base_tree' => $baseTreeSha,
			'tree'      => $treeEntries,
		]);

		if (!$treeRes['ok']) {
			return ['ok' => false, 'error' => 'ツリー作成に失敗: ' . ($treeRes['data']['message'] ?? '')];
		}

		/* 6. コミットを作成 */
		$newCommitRes = self::apiRequest('POST', "/repos/{$repo}/git/commits", [
			'message' => $commitMessage,
			'tree'    => $treeRes['data']['sha'],
			'parents' => [$headSha],
			'author'  => [
				'name'  => self::COMMIT_AUTHOR,
				'email' => self::COMMIT_EMAIL,
				'date'  => date('c'),
			],
		]);

		if (!$newCommitRes['ok']) {
			return ['ok' => false, 'error' => 'コミット作成に失敗: ' . ($newCommitRes['data']['message'] ?? '')];
		}

		/* 7. ブランチ参照を更新 */
		$updateRefRes = self::apiRequest('PATCH', "/repos/{$repo}/git/refs/heads/{$branch}", [
			'sha' => $newCommitRes['data']['sha'],
		]);

		if (!$updateRefRes['ok']) {
			return ['ok' => false, 'error' => '参照更新に失敗: ' . ($updateRefRes['data']['message'] ?? '')];
		}

		/* 最終同期時刻を記録 */
		$cfg['last_sync'] = date('c');
		$cfg['last_sync_direction'] = 'push';
		$cfg['last_commit_sha'] = $newCommitRes['data']['sha'];
		self::saveConfig($cfg);

		$result = [
			'ok'         => true,
			'uploaded'   => $uploaded,
			'commit_sha' => $newCommitRes['data']['sha'],
		];
		if ($errors) $result['errors'] = $errors;

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("Git Push: {$uploaded}件アップロード, コミット " . substr($newCommitRes['data']['sha'], 0, 7));
		}

		return $result;
	}

	/* ══════════════════════════════════════════════
	   コミット履歴
	   ══════════════════════════════════════════════ */

	/** コミット履歴を取得 */
	public static function log(int $limit = 20): array {
		$cfg = self::loadConfig();
		if (!self::isEnabled()) {
			return ['ok' => false, 'error' => 'Git 連携が無効です'];
		}

		$repo = $cfg['repository'];
		$branch = $cfg['branch'] ?? 'main';
		$contentDir = $cfg['content_dir'] ?? 'content';

		$res = self::apiRequest('GET',
			"/repos/{$repo}/commits?sha={$branch}&path={$contentDir}&per_page={$limit}"
		);

		if (!$res['ok']) {
			return ['ok' => false, 'error' => 'コミット履歴の取得に失敗'];
		}

		$commits = [];
		foreach ($res['data'] as $c) {
			$commits[] = [
				'sha'     => substr($c['sha'] ?? '', 0, 7),
				'full_sha'=> $c['sha'] ?? '',
				'message' => $c['commit']['message'] ?? '',
				'author'  => $c['commit']['author']['name'] ?? '',
				'date'    => $c['commit']['author']['date'] ?? '',
				'url'     => $c['html_url'] ?? '',
			];
		}

		return ['ok' => true, 'commits' => $commits];
	}

	/* ══════════════════════════════════════════════
	   ステータス
	   ══════════════════════════════════════════════ */

	/** Git 連携の状態を取得 */
	public static function status(): array {
		$cfg = self::loadConfig();
		return [
			'ok'             => true,
			'enabled'        => self::isEnabled(),
			'repository'     => $cfg['repository'] ?? '',
			'branch'         => $cfg['branch'] ?? 'main',
			'content_dir'    => $cfg['content_dir'] ?? 'content',
			'last_sync'      => $cfg['last_sync'] ?? null,
			'last_direction' => $cfg['last_sync_direction'] ?? null,
			'last_commit'    => $cfg['last_commit_sha'] ?? null,
			'has_git'        => self::hasGitCommand(),
		];
	}

	/* ══════════════════════════════════════════════
	   プレビューブランチ（pitcms 編集セッション相当）
	   ══════════════════════════════════════════════ */

	/**
	 * プレビュー用ブランチを作成。
	 * pitcms の「編集セッション」に相当する機能。
	 */
	public static function createPreviewBranch(string $name): array {
		$cfg = self::loadConfig();
		if (!self::isEnabled()) {
			return ['ok' => false, 'error' => 'Git 連携が無効です'];
		}

		$repo = $cfg['repository'];
		$baseBranch = $cfg['branch'] ?? 'main';
		$branchName = 'preview/' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $name);

		/* ベースブランチの HEAD を取得 */
		$refRes = self::apiRequest('GET', "/repos/{$repo}/git/ref/heads/{$baseBranch}");
		if (!$refRes['ok']) {
			return ['ok' => false, 'error' => 'ベースブランチの取得に失敗'];
		}
		$baseSha = $refRes['data']['object']['sha'] ?? '';

		/* 新しいブランチ参照を作成 */
		$createRes = self::apiRequest('POST', "/repos/{$repo}/git/refs", [
			'ref' => 'refs/heads/' . $branchName,
			'sha' => $baseSha,
		]);

		if (!$createRes['ok']) {
			if (($createRes['data']['message'] ?? '') === 'Reference already exists') {
				return ['ok' => true, 'branch' => $branchName, 'existed' => true];
			}
			return ['ok' => false, 'error' => 'ブランチ作成に失敗: ' . ($createRes['data']['message'] ?? '')];
		}

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("プレビューブランチ作成: {$branchName}");
		}

		return ['ok' => true, 'branch' => $branchName, 'existed' => false];
	}

	/** プレビューブランチを main にマージ */
	public static function mergePreviewBranch(string $branchName): array {
		$cfg = self::loadConfig();
		if (!self::isEnabled()) {
			return ['ok' => false, 'error' => 'Git 連携が無効です'];
		}

		$repo = $cfg['repository'];
		$baseBranch = $cfg['branch'] ?? 'main';

		$mergeRes = self::apiRequest('POST', "/repos/{$repo}/merges", [
			'base'           => $baseBranch,
			'head'           => $branchName,
			'commit_message' => "Merge {$branchName} into {$baseBranch}",
		]);

		if (!$mergeRes['ok']) {
			return ['ok' => false, 'error' => 'マージに失敗: ' . ($mergeRes['data']['message'] ?? '')];
		}

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("プレビューブランチマージ: {$branchName} → {$baseBranch}");
		}

		return ['ok' => true, 'sha' => $mergeRes['data']['sha'] ?? ''];
	}

	/* ══════════════════════════════════════════════
	   GitHub Issue（お問い合わせ保存）
	   ══════════════════════════════════════════════ */

	/**
	 * GitHub Issue を作成。
	 * pitcms のお問い合わせ → GitHub Issue 保存に相当。
	 */
	public static function createIssue(string $title, string $body, array $labels = []): array {
		$cfg = self::loadConfig();
		if (!self::isEnabled()) {
			return ['ok' => false, 'error' => 'Git 連携が無効です'];
		}
		if (empty($cfg['issues_enabled'])) {
			return ['ok' => false, 'error' => 'Issue 連携が無効です'];
		}

		$repo = $cfg['repository'];

		$issueData = [
			'title' => $title,
			'body'  => $body,
		];
		if ($labels) $issueData['labels'] = $labels;

		$res = self::apiRequest('POST', "/repos/{$repo}/issues", $issueData);

		if (!$res['ok']) {
			return ['ok' => false, 'error' => 'Issue 作成に失敗: ' . ($res['data']['message'] ?? '')];
		}

		return [
			'ok'     => true,
			'number' => $res['data']['number'] ?? 0,
			'url'    => $res['data']['html_url'] ?? '',
		];
	}

	/* ══════════════════════════════════════════════
	   ファイル収集ヘルパー
	   ══════════════════════════════════════════════ */

	/**
	 * ディレクトリ内の .md と .json ファイルを再帰的に収集。
	 * @return array<string, string> relativePath => absolutePath
	 */
	private static function collectLocalFiles(string $baseDir): array {
		$files = [];
		if (!is_dir($baseDir)) return $files;

		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iter as $item) {
			if ($item->isDir()) continue;
			$ext = strtolower($item->getExtension());
			if ($ext !== 'md' && $ext !== 'json') continue;

			$relativePath = substr($item->getPathname(), strlen($baseDir) + 1);
			/* pages.json はレガシーファイルなので除外（コレクションのみ同期） */
			if ($relativePath === 'pages.json') continue;

			$files[$relativePath] = $item->getPathname();
		}

		return $files;
	}

	/* ══════════════════════════════════════════════
	   POST アクションハンドラ
	   ══════════════════════════════════════════════ */

	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';
		$valid = [
			'git_configure', 'git_test', 'git_pull', 'git_push',
			'git_log', 'git_status', 'git_preview_branch',
		];
		if (!in_array($action, $valid, true)) return;

		if (!AdminEngine::isLoggedIn()) {
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['error' => '未ログイン']);
			exit;
		}
		AdminEngine::verifyCsrf();
		header('Content-Type: application/json; charset=UTF-8');

		$result = match ($action) {
			'git_configure'      => self::handleConfigure(),
			'git_test'           => self::testConnection(),
			'git_pull'           => self::pull(),
			'git_push'           => self::push(trim($_POST['message'] ?? '')),
			/* M24 fix: limit のバウンドチェック */
			'git_log'            => self::log(max(1, min(100, (int)($_POST['limit'] ?? 20)))),
			'git_status'         => self::status(),
			'git_preview_branch' => self::createPreviewBranch(trim($_POST['name'] ?? 'draft')),
		};

		echo json_encode($result, JSON_UNESCAPED_UNICODE);
		exit;
	}

	private static function handleConfigure(): array {
		$repository = trim($_POST['repository'] ?? '');
		$token = trim($_POST['token'] ?? '');
		$branch = trim($_POST['branch'] ?? '') ?: 'main';
		$contentDir = trim($_POST['content_dir'] ?? '') ?: 'content';
		$enabled = !empty($_POST['enabled']);
		$issuesEnabled = !empty($_POST['issues_enabled']);
		$webhookSecret = trim($_POST['webhook_secret'] ?? '');

		if ($enabled && ($repository === '' || $token === '')) {
			return ['ok' => false, 'error' => 'リポジトリとトークンは必須です'];
		}
		/* C21 fix: リポジトリ名の形式を厳密に検証（owner/repo） */
		if ($repository !== '' && !preg_match('#^[a-zA-Z0-9_.\-]+/[a-zA-Z0-9_.\-]+$#', $repository)) {
			return ['ok' => false, 'error' => 'リポジトリは owner/repo 形式で入力してください'];
		}
		/* C22 fix: content_dir のパストラバーサル防止 */
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $contentDir)) {
			return ['ok' => false, 'error' => 'content_dir に不正な文字が含まれています'];
		}
		/* M22 fix: ブランチ名の検証 */
		if (!preg_match('/^[a-zA-Z0-9_.\-\/]+$/', $branch) || str_contains($branch, '..')) {
			return ['ok' => false, 'error' => 'ブランチ名に不正な文字が含まれています'];
		}

		/* トークンが「********」の場合は既存値を維持 */
		$cfg = self::loadConfig();
		if ($token === '********' || $token === '') {
			$token = $cfg['token'] ?? '';
		}

		$newConfig = [
			'enabled'        => $enabled,
			'provider'       => 'github',
			'repository'     => $repository,
			'branch'         => $branch,
			'token'          => $token,
			'content_dir'    => $contentDir,
			'issues_enabled' => $issuesEnabled,
			'webhook_secret' => $webhookSecret,
			'last_sync'      => $cfg['last_sync'] ?? null,
			'last_sync_direction' => $cfg['last_sync_direction'] ?? null,
			'last_commit_sha'     => $cfg['last_commit_sha'] ?? null,
		];

		self::saveConfig($newConfig);

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			$status = $enabled ? '有効' : '無効';
			AdminEngine::logActivity("Git 連携設定変更: {$repository} ({$status})");
		}

		return ['ok' => true, 'message' => 'Git 連携設定を保存しました'];
	}
}
