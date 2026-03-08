<?php
/**
 * StaticEngine - 静的サイト生成エンジン
 *
 * コンテンツを静的 HTML ファイルとして書き出す。
 * 差分ビルド（content_hash / settings_hash）で変更ページのみ再生成。
 * TemplateEngine + ThemeEngine::buildStaticContext() でレンダリング。
 */
class StaticEngine {
	private const OUTPUT_DIR     = 'static';
	private const BUILD_STATE    = 'static_build.json';
	private const SLUG_PATTERN   = '/^[a-zA-Z0-9_-]+$/';

	private array  $settings   = [];
	private array  $pages      = [];
	private string $themeDir   = '';
	private array  $buildState = [];
	private array  $warnings   = [];

	/* ══════════════════════════════════════════════
	   ap_action ディスパッチャ
	   ══════════════════════════════════════════════ */

	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';
		$valid  = [
			'generate_static_diff', 'generate_static_full',
			'clean_static', 'build_zip', 'static_status',
		];
		if (!in_array($action, $valid, true)) return;

		if (!isset($_SESSION['l']) || $_SESSION['l'] !== true) {
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['error' => '未ログイン']);
			exit;
		}
		AdminEngine::verifyCsrf();
		header('Content-Type: application/json; charset=UTF-8');

		$engine = new self();
		$engine->init();

		match ($action) {
			'generate_static_diff' => self::respond($engine->buildDiff()),
			'generate_static_full' => self::respond($engine->buildAll()),
			'clean_static'         => self::respondClean($engine),
			'build_zip'            => $engine->serveZip(),
			'static_status'        => self::respond($engine->getStatus()),
		};
	}

	/* ══════════════════════════════════════════════
	   初期化
	   ══════════════════════════════════════════════ */

	private function init(): void {
		$this->settings = json_read('settings.json', settings_dir());
		$this->pages    = json_read('pages.json', content_dir());
		$theme          = $this->settings['themeSelect'] ?? 'AP-Default';
		if (!preg_match('/^[a-zA-Z0-9_-]+$/', $theme)) $theme = 'AP-Default';
		$this->themeDir = 'themes/' . $theme;
		if (!is_dir($this->themeDir)) {
			$this->themeDir = 'themes/AP-Default';
		}
		$this->loadBuildState();
	}

	/* ══════════════════════════════════════════════
	   差分ビルド
	   ══════════════════════════════════════════════ */

	public function buildDiff(): array {
		$start = hrtime(true);
		$built = 0; $skipped = 0;

		$newSettingsHash = $this->computeSettingsHash();
		$allDirty = ($newSettingsHash !== ($this->buildState['settings_hash'] ?? ''));
		$themeChanged = (($this->settings['themeSelect'] ?? '') !== ($this->buildState['theme'] ?? ''));

		if ($themeChanged || $allDirty) {
			$this->copyAssets();
		}
		$this->buildState['settings_hash'] = $newSettingsHash;
		$this->buildState['theme'] = $this->settings['themeSelect'] ?? 'AP-Default';

		foreach ($this->pages as $slug => $content) {
			if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
			$contentHash = $this->computeContentHash($slug, (string)$content, $newSettingsHash);
			$outputPath  = self::OUTPUT_DIR . '/' . ($slug === 'index' ? '' : $slug . '/') . 'index.html';

			if (!$allDirty
				&& isset($this->buildState['pages'][$slug]['content_hash'])
				&& $this->buildState['pages'][$slug]['content_hash'] === $contentHash
				&& file_exists($outputPath)) {
				$skipped++;
				continue;
			}

			$this->buildPage($slug, (string)$content);
			$this->buildState['pages'][$slug] = [
				'content_hash' => $contentHash,
				'built_at'     => date('c'),
			];
			$built++;
		}

		$deleted = $this->deleteOrphanedFiles();
		$this->buildState['last_diff_build'] = date('c');
		$this->saveBuildState();

		$elapsed = (int)((hrtime(true) - $start) / 1_000_000);
		$result = ['ok' => true, 'built' => $built, 'skipped' => $skipped, 'deleted' => $deleted, 'elapsed_ms' => $elapsed];
		if ($this->warnings) $result['warnings'] = $this->warnings;
		return $result;
	}

	/* ══════════════════════════════════════════════
	   フルビルド
	   ══════════════════════════════════════════════ */

	public function buildAll(): array {
		$start = hrtime(true);
		$built = 0;

		$this->copyAssets();
		$newSettingsHash = $this->computeSettingsHash();
		$this->buildState['settings_hash'] = $newSettingsHash;
		$this->buildState['theme'] = $this->settings['themeSelect'] ?? 'AP-Default';
		$this->buildState['pages'] = [];

		foreach ($this->pages as $slug => $content) {
			if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
			$this->buildPage($slug, (string)$content);
			$this->buildState['pages'][$slug] = [
				'content_hash' => $this->computeContentHash($slug, (string)$content, $newSettingsHash),
				'built_at'     => date('c'),
			];
			$built++;
		}

		$deleted = $this->deleteOrphanedFiles();
		$this->buildState['last_full_build'] = date('c');
		$this->buildState['last_diff_build'] = date('c');
		$this->saveBuildState();

		$elapsed = (int)((hrtime(true) - $start) / 1_000_000);
		$result = ['ok' => true, 'built' => $built, 'skipped' => 0, 'deleted' => $deleted, 'elapsed_ms' => $elapsed];
		if ($this->warnings) $result['warnings'] = $this->warnings;
		return $result;
	}

	/* ══════════════════════════════════════════════
	   クリーン
	   ══════════════════════════════════════════════ */

	public function clean(): void {
		if (is_dir(self::OUTPUT_DIR)) {
			$this->removeDir(self::OUTPUT_DIR);
		}
		$this->buildState = ['schema_version' => 1, 'pages' => []];
		$this->saveBuildState();
	}

	/* ══════════════════════════════════════════════
	   ステータス
	   ══════════════════════════════════════════════ */

	public function getStatus(): array {
		$pageStatuses = [];
		$settingsHash = $this->computeSettingsHash();
		$allDirty = ($settingsHash !== ($this->buildState['settings_hash'] ?? ''));

		foreach ($this->pages as $slug => $content) {
			if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
			$contentHash = $this->computeContentHash($slug, (string)$content, $settingsHash);
			$outputPath  = self::OUTPUT_DIR . '/' . ($slug === 'index' ? '' : $slug . '/') . 'index.html';
			$state = 'not_built';
			if (file_exists($outputPath)) {
				if (!$allDirty
					&& isset($this->buildState['pages'][$slug]['content_hash'])
					&& $this->buildState['pages'][$slug]['content_hash'] === $contentHash) {
					$state = 'current';
				} else {
					$state = 'outdated';
				}
			}
			$pageStatuses[] = [
				'slug'  => $slug,
				'state' => $state,
			];
		}

		return [
			'ok'              => true,
			'last_full_build' => $this->buildState['last_full_build'] ?? null,
			'last_diff_build' => $this->buildState['last_diff_build'] ?? null,
			'theme'           => $this->buildState['theme'] ?? null,
			'pages'           => $pageStatuses,
			'static_exists'   => is_dir(self::OUTPUT_DIR) && glob(self::OUTPUT_DIR . '/*/index.html'),
		];
	}

	/* ══════════════════════════════════════════════
	   アセットコピー
	   ══════════════════════════════════════════════ */

	public function copyAssets(): void {
		$assetsDir = self::OUTPUT_DIR . '/assets';
		$this->ensureDir($assetsDir);

		/* テーマ CSS */
		$css = $this->themeDir . '/style.css';
		if (file_exists($css)) {
			copy($css, $assetsDir . '/style.css');
		}

		/* テーマ JS（style.css 以外の .js ファイル） */
		foreach (glob($this->themeDir . '/*.js') ?: [] as $js) {
			copy($js, $assetsDir . '/' . basename($js));
		}

		/* 公開 API クライアント JS */
		$apiClient = 'engines/JsEngine/ap-api-client.js';
		if (file_exists($apiClient)) {
			copy($apiClient, $assetsDir . '/ap-api-client.js');
		}

		/* uploads/ 差分コピー */
		$this->syncUploads();

		/* static/.htaccess 自動生成 */
		$this->writeStaticHtaccess();

		$this->buildState['assets_copied_at'] = date('c');
	}

	/* ══════════════════════════════════════════════
	   ZIP ダウンロード
	   ══════════════════════════════════════════════ */

	private function serveZip(): never {
		if (!is_dir(self::OUTPUT_DIR)) {
			http_response_code(400);
			echo json_encode(['error' => '静的ファイルが生成されていません。先にビルドを実行してください。']);
			exit;
		}
		if (!class_exists('ZipArchive')) {
			http_response_code(500);
			echo json_encode(['error' => 'ZipArchive 拡張が利用できません。']);
			exit;
		}
		$tmpFile = sys_get_temp_dir() . '/ap_static_' . date('Ymd_His') . '.zip';
		$zip = new ZipArchive();
		if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			http_response_code(500);
			echo json_encode(['error' => 'ZIP ファイルの作成に失敗しました。']);
			exit;
		}
		$this->addDirToZip($zip, self::OUTPUT_DIR, self::OUTPUT_DIR);
		$zip->close();

		try {
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="static-' . date('Ymd') . '.zip"');
			header('Content-Length: ' . filesize($tmpFile));
			readfile($tmpFile);
		} finally {
			@unlink($tmpFile);
		}
		exit;
	}

	/* ══════════════════════════════════════════════
	   内部メソッド — ページビルド
	   ══════════════════════════════════════════════ */

	private function buildPage(string $slug, string $content): void {
		$html = $this->renderPage($slug, $content);
		$dir  = self::OUTPUT_DIR . '/' . ($slug === 'index' ? '' : $slug);
		if ($dir !== self::OUTPUT_DIR) {
			$this->ensureDir($dir);
		} else {
			$this->ensureDir(self::OUTPUT_DIR);
		}
		$path = $dir . '/index.html';
		file_put_contents($path, $html, LOCK_EX);
	}

	private function renderPage(string $slug, string $content): string {
		$tplPath = $this->themeDir . '/theme.html';
		if (!file_exists($tplPath)) {
			$tplPath = 'themes/AP-Default/theme.html';
		}
		$tpl = file_get_contents($tplPath);
		if ($tpl === false) {
			$msg = "テンプレート読み込みエラー: {$tplPath}";
			error_log("StaticEngine: {$msg}");
			$this->warnings[] = $msg;
			return '<!-- StaticEngine: ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . ' -->';
		}

		$context = ThemeEngine::buildStaticContext($slug, $content, $this->settings);
		$html = TemplateEngine::render($tpl, $context, dirname($tplPath));
		return $this->rewriteAssetPaths($html);
	}

	private function rewriteAssetPaths(string $html): string {
		$theme = $this->settings['themeSelect'] ?? 'AP-Default';

		/* テーマ CSS パスを書き換え */
		$html = str_replace(
			'href="themes/' . $theme . '/style.css"',
			'href="/assets/style.css"',
			$html
		);
		$html = str_replace(
			"href='themes/" . $theme . "/style.css'",
			"href='/assets/style.css'",
			$html
		);

		/* uploads/ パスを書き換え */
		$html = preg_replace(
			'#(src=["\'])uploads/#',
			'$1/assets/uploads/',
			$html
		) ?? $html;

		return $html;
	}

	/* ══════════════════════════════════════════════
	   内部メソッド — ハッシュ・差分判定
	   ══════════════════════════════════════════════ */

	private function computeSettingsHash(): string {
		$keys = ['title', 'description', 'keywords', 'copyright', 'themeSelect', 'menu', 'subside'];
		$data = [];
		foreach ($keys as $k) {
			$data[$k] = $this->settings[$k] ?? '';
		}
		return md5(json_encode($data));
	}

	private function computeContentHash(string $slug, string $content, string $settingsHash): string {
		return md5($slug . $content . $settingsHash);
	}

	/* ══════════════════════════════════════════════
	   内部メソッド — ビルド状態
	   ══════════════════════════════════════════════ */

	private function loadBuildState(): void {
		$this->buildState = json_read(self::BUILD_STATE, settings_dir());
		if (empty($this->buildState['schema_version'])) {
			$this->buildState = ['schema_version' => 1, 'pages' => []];
		}
	}

	private function saveBuildState(): void {
		json_write(self::BUILD_STATE, $this->buildState, settings_dir());
	}

	/* ══════════════════════════════════════════════
	   内部メソッド — ファイル操作
	   ══════════════════════════════════════════════ */

	private function deleteOrphanedFiles(): int {
		$deleted = 0;
		if (!is_dir(self::OUTPUT_DIR)) return 0;

		/* static/ 直下の index.html */
		$topIndex = self::OUTPUT_DIR . '/index.html';
		if (file_exists($topIndex) && !isset($this->pages['index'])) {
			@unlink($topIndex);
			$deleted++;
		}

		/* static/{slug}/ ディレクトリ */
		$dirs = glob(self::OUTPUT_DIR . '/*', GLOB_ONLYDIR) ?: [];
		foreach ($dirs as $dir) {
			$slug = basename($dir);
			if ($slug === 'assets') continue;
			if (!isset($this->pages[$slug])) {
				$this->removeDir($dir);
				$deleted++;
			}
		}
		return $deleted;
	}

	private function syncUploads(): void {
		$src = 'uploads';
		$dst = self::OUTPUT_DIR . '/assets/uploads';
		if (!is_dir($src)) return;
		$this->ensureDir($dst);

		$files = glob($src . '/*') ?: [];
		foreach ($files as $f) {
			if (is_dir($f)) continue;
			$base = basename($f);
			if ($base === '.htaccess') continue;
			$target = $dst . '/' . $base;
			if (!file_exists($target) || filemtime($f) > filemtime($target)) {
				copy($f, $target);
			}
		}
	}

	private function writeStaticHtaccess(): void {
		$htaccess = self::OUTPUT_DIR . '/.htaccess';
		$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/') . '/';
		$content = "Options -Indexes -ExecCGI\n\n"
			. "# PHP 実行禁止\n"
			. "<FilesMatch \"\\.php$\">\n"
			. "    Require all denied\n"
			. "</FilesMatch>\n\n"
			. "# 未ビルドページは index.php にフォールバック\n"
			. "ErrorDocument 404 " . $basePath . "index.php\n";
		file_put_contents($htaccess, $content, LOCK_EX);
	}

	private function ensureDir(string $path): void {
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}
	}

	private function removeDir(string $dir): void {
		$real = realpath($dir);
		if ($real === false) return;
		/* 安全チェック: プロジェクトルート内のみ削除可 */
		$projectRoot = realpath('.');
		if ($projectRoot === false || !str_starts_with($real, $projectRoot)) return;

		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iter as $f) {
			$f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
		}
		@rmdir($real);
	}

	private function addDirToZip(ZipArchive $zip, string $dir, string $base): void {
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ($iter as $item) {
			$rel = substr($item->getPathname(), strlen($base) + 1);
			if ($item->isDir()) {
				$zip->addEmptyDir($rel);
			} else {
				$zip->addFile($item->getPathname(), $rel);
			}
		}
	}

	/* ══════════════════════════════════════════════
	   ヘルパー — JSON レスポンス
	   ══════════════════════════════════════════════ */

	private static function respond(array $data): never {
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
		exit;
	}

	private static function respondClean(self $engine): never {
		$engine->clean();
		echo json_encode(['ok' => true, 'message' => '静的ファイルを削除しました。']);
		exit;
	}
}
