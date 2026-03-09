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
	private const SLUG_PATTERN   = '/^[a-zA-Z0-9_\-]+(\/[a-zA-Z0-9_\-]+)*$/';

	private array  $settings     = [];
	private array  $pages        = [];
	private string $themeDir     = '';
	private array  $buildState   = [];
	private array  $warnings     = [];
	private array  $changedFiles = [];

	/* ══════════════════════════════════════════════
	   ap_action ディスパッチャ
	   ══════════════════════════════════════════════ */

	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';
		$valid  = [
			'generate_static_diff', 'generate_static_full',
			'clean_static', 'build_zip', 'static_status', 'deploy_diff',
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
			'deploy_diff'          => $engine->serveDiffZip(),
		};
	}

	/* ══════════════════════════════════════════════
	   初期化
	   ══════════════════════════════════════════════ */

	private function init(): void {
		$this->settings = json_read('settings.json', settings_dir());

		/* コレクションモードか従来モードかを判定 */
		if (class_exists('CollectionEngine') && CollectionEngine::isEnabled()) {
			$this->pages = CollectionEngine::loadAllAsPages();
			/* レガシーページを追加（コレクション未移行分） */
			$legacy = json_read('pages.json', content_dir());
			foreach ($legacy as $slug => $content) {
				if (!isset($this->pages[$slug])) {
					$this->pages[$slug] = $content;
				}
			}
		} else {
			$this->pages = json_read('pages.json', content_dir());
		}

		$theme = $this->settings['themeSelect'] ?? 'AP-Default';
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

		/* ビルドフック: before_build（C3 fix: ページ生成前に移動） */
		$this->runHook('before_build', ['type' => 'diff']);

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

		/* コレクション一覧ページを生成（ページネーション対応） */
		$built += $this->buildCollectionIndexes();

		/* タグページを生成 */
		$built += $this->buildTagPages();

		/* SEO ファイル生成 */
		$this->generateSitemap();
		$this->generateRobotsTxt();
		$this->generate404Page();
		$this->generateSearchIndex();
		$this->generateRedirects();
		$deleted = $this->deleteOrphanedFiles();
		$this->buildState['last_diff_build'] = date('c');
		$this->buildState['changed_files'] = $this->changedFiles;
		$this->saveBuildState();

		/* ビルドフック: after_build */
		$this->runHook('after_build', ['type' => 'diff', 'built' => $built]);

		/* Webhook: build.completed */
		if (class_exists('WebhookEngine')) {
			WebhookEngine::dispatch('build.completed', ['type' => 'diff', 'built' => $built, 'skipped' => $skipped]);
		}

		$elapsed = (int)((hrtime(true) - $start) / 1_000_000);
		if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('performance', 'StaticEngine 差分ビルド完了', ['built' => $built, 'skipped' => $skipped, 'deleted' => $deleted, 'elapsed_ms' => $elapsed]);
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

		/* ビルドフック: before_build（C3 fix: ページ生成前に移動） */
		$this->runHook('before_build', ['type' => 'full']);

		foreach ($this->pages as $slug => $content) {
			if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
			$this->buildPage($slug, (string)$content);
			$this->buildState['pages'][$slug] = [
				'content_hash' => $this->computeContentHash($slug, (string)$content, $newSettingsHash),
				'built_at'     => date('c'),
			];
			$built++;
		}

		/* コレクション一覧ページを生成（ページネーション対応） */
		$built += $this->buildCollectionIndexes();

		/* タグページを生成 */
		$built += $this->buildTagPages();

		/* SEO ファイル生成 */
		$this->generateSitemap();
		$this->generateRobotsTxt();
		$this->generate404Page();
		$this->generateSearchIndex();
		$this->generateRedirects();
		$deleted = $this->deleteOrphanedFiles();
		$this->buildState['last_full_build'] = date('c');
		$this->buildState['last_diff_build'] = date('c');
		$this->buildState['changed_files'] = $this->changedFiles;
		$this->saveBuildState();

		/* ビルドフック: after_build */
		$this->runHook('after_build', ['type' => 'full', 'built' => $built]);

		/* Webhook: build.completed */
		if (class_exists('WebhookEngine')) {
			WebhookEngine::dispatch('build.completed', ['type' => 'full', 'built' => $built]);
		}

		$elapsed = (int)((hrtime(true) - $start) / 1_000_000);
		if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('performance', 'StaticEngine フルビルド完了', ['built' => $built, 'deleted' => $deleted, 'elapsed_ms' => $elapsed]);
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
			'static_exists'   => is_dir(self::OUTPUT_DIR) && !empty(glob(self::OUTPUT_DIR . '/*/index.html')),
		];
	}

	/* ══════════════════════════════════════════════
	   アセットコピー
	   ══════════════════════════════════════════════ */

	public function copyAssets(): void {
		$assetsDir = self::OUTPUT_DIR . '/assets';
		$this->ensureDir($assetsDir);

		/* テーマ CSS（ミニファイ対応） */
		$css = $this->themeDir . '/style.css';
		if (file_exists($css)) {
			$cssContent = file_get_contents($css);
			if ($cssContent !== false && !empty($this->settings['minify'] ?? true)) {
				$cssContent = self::minifyCss($cssContent);
			}
			if ($cssContent !== false) {
				file_put_contents($assetsDir . '/style.css', $cssContent, LOCK_EX);
			} else {
				copy($css, $assetsDir . '/style.css');
			}
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

		/* 検索 JS */
		$searchJs = 'engines/JsEngine/ap-search.js';
		if (file_exists($searchJs)) {
			copy($searchJs, $assetsDir . '/ap-search.js');
		}

		/* static/.htaccess 自動生成 */
		$this->writeStaticHtaccess();

		/* ビルドフック: after_asset_copy */
		$this->runHook('after_asset_copy', ['assets_dir' => $assetsDir]);

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
		/* コレクション専用テンプレートを試す */
		$html = $this->renderPageWithCollectionTemplate($slug, $content);
		if ($html === null) {
			$html = $this->renderPage($slug, $content);
		}

		/* HTML ミニファイ */
		if (!empty($this->settings['minify'] ?? true)) {
			$html = self::minifyHtml($html);
		}

		/* ビルドフック: after_page_render */
		$this->runHook('after_page_render', ['slug' => $slug, 'html' => &$html]);

		if ($slug === 'index') {
			$dir = self::OUTPUT_DIR;
		} else {
			$dir = self::OUTPUT_DIR . '/' . $slug;
		}
		$this->ensureDir($dir);
		$path = $dir . '/index.html';
		file_put_contents($path, $html, LOCK_EX);
		$this->changedFiles[] = $path;
	}

	private function renderPage(string $slug, string $content, array $meta = []): string {
		$tplPath = $this->themeDir . '/theme.html';
		if (!file_exists($tplPath)) {
			$tplPath = 'themes/AP-Default/theme.html';
		}
		$tpl = file_get_contents($tplPath);
		if ($tpl === false) {
			$msg = "テンプレート読み込みエラー: {$tplPath}";
			error_log("StaticEngine: {$msg}");
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', $msg, ['template' => $tplPath]);
			$this->warnings[] = $msg;
			return '<!-- StaticEngine: ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . ' -->';
		}

		$context = ThemeEngine::buildStaticContext($slug, $content, $this->settings, $meta);
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
	   コレクション一覧ページ生成
	   ══════════════════════════════════════════════ */

	private function buildCollectionIndexes(): int {
		if (!class_exists('CollectionEngine') || !CollectionEngine::isEnabled()) return 0;

		$built = 0;
		$collections = CollectionEngine::listCollections();

		foreach ($collections as $col) {
			$name = $col['name'];
			if ($name === 'pages') continue;

			$items = CollectionEngine::getItems($name);
			$perPage = (int)($col['perPage'] ?? 10);
			if ($perPage < 1) $perPage = 10;

			$allItems = array_values(array_map(function($slug, $item) use ($name) {
				$preview = strip_tags($item['html']);
				if (mb_strlen($preview, 'UTF-8') > 200) {
					$preview = mb_substr($preview, 0, 200, 'UTF-8') . '...';
				}
				return array_merge($item['meta'], [
					'slug'    => $slug,
					'preview' => $preview,
					'link'    => '/' . $name . '/' . $slug . '/',
					'html'    => $item['html'],
				]);
			}, array_keys($items), array_values($items)));

			$totalItems = count($allItems);
			$totalPages = max(1, (int)ceil($totalItems / $perPage));

			for ($page = 1; $page <= $totalPages; $page++) {
				$offset = ($page - 1) * $perPage;
				$pageItems = array_slice($allItems, $offset, $perPage);

				$pagination = [
					'current_page' => $page,
					'total_pages'  => $totalPages,
					'per_page'     => $perPage,
					'total_items'  => $totalItems,
					'has_prev'     => $page > 1,
					'has_next'     => $page < $totalPages,
					'prev_url'     => $page === 2 ? '/' . $name . '/' : '/' . $name . '/page/' . ($page - 1) . '/',
					'next_url'     => '/' . $name . '/page/' . ($page + 1) . '/',
				];

				$indexContent = $this->renderCollectionIndex($col, $pageItems, $pagination, $allItems);

				/* C5 fix: page 1 も page 2+ と同じパスでレンダリング（二重レンダリング防止） */
				if ($page === 1) {
					$slug = $name;
					$dir = self::OUTPUT_DIR . '/' . $slug;
				} else {
					$slug = $name . '/page/' . $page;
					$dir = self::OUTPUT_DIR . '/' . $slug;
				}
				$this->ensureDir($dir);
				$path = $dir . '/index.html';
				$meta = ['title' => ($col['label'] ?? $name) . ($page > 1 ? ' - ' . $page . 'ページ' : '')];
				$rendered = $this->renderPage($slug, $indexContent, $meta);
				if (!empty($this->settings['minify'] ?? true)) {
					$rendered = self::minifyHtml($rendered);
				}
				file_put_contents($path, $rendered, LOCK_EX);
				$this->changedFiles[] = $path;
				$built++;
			}
		}
		return $built;
	}

	private function renderCollectionIndex(array $col, array $pageItems, array $pagination = [], array $allItems = []): string {
		$name = $col['name'];
		$template = $col['template'] ?? null;

		/* コレクション専用テンプレートを検索 */
		$customTpl = $this->themeDir . '/collection-' . $name . '-index.html';
		if ($template) {
			$customTpl = $this->themeDir . '/' . $template . '-index.html';
		}

		/* 全タグを収集 */
		$allTags = $this->collectTags($allItems ?: $pageItems);

		if (file_exists($customTpl)) {
			$tplContent = file_get_contents($customTpl);
			if ($tplContent !== false) {
				$ctx = array_merge(
					ThemeEngine::buildStaticContext($name, '', $this->settings, ['title' => $col['label'] ?? $name]),
					[
						'collection_name'  => $name,
						'collection_label' => $col['label'] ?? $name,
						'items'            => $pageItems,
						'item_count'       => count($pageItems),
						'pagination'       => $pagination,
						'all_tags'         => $allTags,
					]
				);
				return TemplateEngine::render($tplContent, $ctx, $this->themeDir);
			}
		}

		/* デフォルトテンプレート */
		$label = htmlspecialchars($col['label'] ?? $name, ENT_QUOTES, 'UTF-8');
		$html  = "<h1>{$label}</h1>\n<div class=\"ap-collection-index\">\n";

		foreach ($pageItems as $item) {
			$slug    = $item['slug'] ?? '';
			$title   = htmlspecialchars($item['title'] ?? $slug, ENT_QUOTES, 'UTF-8');
			$date    = htmlspecialchars($item['date'] ?? '', ENT_QUOTES, 'UTF-8');
			$preview = htmlspecialchars($item['preview'] ?? '', ENT_QUOTES, 'UTF-8');
			$link    = $item['link'] ?? ('/' . $name . '/' . $slug . '/');

			$html .= "<article class=\"ap-collection-entry\">\n";
			$html .= "  <h2><a href=\"{$link}\">{$title}</a></h2>\n";
			if ($date) $html .= "  <time>{$date}</time>\n";
			$html .= "  <p>{$preview}</p>\n";
			$html .= "</article>\n";
		}

		$html .= "</div>\n";

		/* ページネーション HTML */
		if (!empty($pagination) && $pagination['total_pages'] > 1) {
			$html .= "<nav class=\"ap-pagination\">\n";
			if ($pagination['has_prev']) {
				$html .= '  <a href="' . htmlspecialchars($pagination['prev_url'], ENT_QUOTES, 'UTF-8') . '" class="ap-pagination-prev">&laquo; 前</a>' . "\n";
			}
			$html .= '  <span class="ap-pagination-info">' . $pagination['current_page'] . ' / ' . $pagination['total_pages'] . '</span>' . "\n";
			if ($pagination['has_next']) {
				$html .= '  <a href="' . htmlspecialchars($pagination['next_url'], ENT_QUOTES, 'UTF-8') . '" class="ap-pagination-next">次 &raquo;</a>' . "\n";
			}
			$html .= "</nav>\n";
		}

		return $html;
	}

	/**
	 * 個別コレクションアイテムをカスタムテンプレートでレンダリング
	 * 前後ナビゲーション + OGP メタ情報を含む
	 */
	private function renderPageWithCollectionTemplate(string $slug, string $content): ?string {
		if (!str_contains($slug, '/')) return null;
		$parts = explode('/', $slug, 2);
		$colName = $parts[0];
		$itemSlug = $parts[1];

		if (!class_exists('CollectionEngine')) return null;
		$def = CollectionEngine::getCollectionDef($colName);
		if ($def === null) return null;

		$template = $def['template'] ?? null;
		$singleTpl = $this->themeDir . '/collection-' . $colName . '-single.html';
		if ($template) {
			$singleTpl = $this->themeDir . '/' . $template . '-single.html';
		}

		if (!file_exists($singleTpl)) return null;

		$tplContent = file_get_contents($singleTpl);
		if ($tplContent === false) return null;

		$item = CollectionEngine::getItem($colName, $itemSlug);
		$meta = $item ? $item['meta'] : [];

		/* 前後記事ナビゲーション */
		$prevNext = $this->getPrevNextItems($colName, $itemSlug);

		$ctx = array_merge(
			ThemeEngine::buildStaticContext($slug, $content, $this->settings, $meta),
			$meta,
			[
				'collection_name'  => $colName,
				'collection_label' => $def['label'] ?? $colName,
				'item_slug'        => $itemSlug,
				'prev_item'        => $prevNext['prev'],
				'next_item'        => $prevNext['next'],
			]
		);
		return TemplateEngine::render($tplContent, $ctx, $this->themeDir);
	}

	/**
	 * 前後アイテムを取得（日付降順）
	 */
	private function getPrevNextItems(string $colName, string $currentSlug): array {
		$items = CollectionEngine::getItems($colName);
		$slugs = array_keys($items);
		$idx = array_search($currentSlug, $slugs, true);
		$result = ['prev' => null, 'next' => null];
		if ($idx === false) return $result;

		if ($idx > 0) {
			$prevSlug = $slugs[$idx - 1];
			$prevItem = $items[$prevSlug];
			$result['prev'] = [
				'slug'  => $prevSlug,
				'title' => $prevItem['meta']['title'] ?? $prevSlug,
				'url'   => '/' . $colName . '/' . $prevSlug . '/',
				'date'  => $prevItem['meta']['date'] ?? '',
			];
		}
		if ($idx < count($slugs) - 1) {
			$nextSlug = $slugs[$idx + 1];
			$nextItem = $items[$nextSlug];
			$result['next'] = [
				'slug'  => $nextSlug,
				'title' => $nextItem['meta']['title'] ?? $nextSlug,
				'url'   => '/' . $colName . '/' . $nextSlug . '/',
				'date'  => $nextItem['meta']['date'] ?? '',
			];
		}
		return $result;
	}

	/* ══════════════════════════════════════════════
	   SEO: sitemap.xml / robots.txt
	   ══════════════════════════════════════════════ */

	private function generateSitemap(): void {
		$baseUrl = rtrim($this->settings['site_url'] ?? '', '/');
		if ($baseUrl === '') {
			$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
			$baseUrl = $proto . '://' . $host;
		}

		$xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

		foreach ($this->pages as $slug => $content) {
			if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
			$loc = $baseUrl . '/' . ($slug === 'index' ? '' : $slug . '/');
			$xml .= "  <url><loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc></url>\n";
		}

		/* コレクション一覧 + タグページ */
		if (class_exists('CollectionEngine') && CollectionEngine::isEnabled()) {
			$collections = CollectionEngine::listCollections();
			foreach ($collections as $col) {
				if ($col['name'] === 'pages') continue;
				$colName = $col['name'];
				$xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/', ENT_XML1, 'UTF-8') . "</loc></url>\n";

				/* ページネーション */
				$items = CollectionEngine::getItems($colName);
				$perPage = (int)($col['perPage'] ?? 10);
				$totalPages = max(1, (int)ceil(count($items) / max(1, $perPage)));
				for ($p = 2; $p <= $totalPages; $p++) {
					$xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/page/' . $p . '/', ENT_XML1, 'UTF-8') . "</loc></url>\n";
				}

				/* タグページ */
				$tagSlugs = [];
				foreach ($items as $item) {
					$tags = $item['meta']['tags'] ?? [];
					if (is_string($tags)) $tags = array_map('trim', explode(',', $tags));
					foreach ($tags as $tag) {
						$tag = trim($tag);
						if ($tag === '') continue;
						$ts_ = preg_replace('/[^a-z0-9\-_\p{L}\p{N}]/u', '', mb_convert_case(str_replace(' ', '-', $tag), MB_CASE_LOWER, 'UTF-8'));
						if ($ts_ !== '' && !str_contains($ts_, '..')) $tagSlugs[$ts_] = true;
					}
				}
				if ($tagSlugs) {
					$xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/tags/', ENT_XML1, 'UTF-8') . "</loc></url>\n";
					foreach (array_keys($tagSlugs) as $ts) {
						$xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/tag/' . $ts . '/', ENT_XML1, 'UTF-8') . "</loc></url>\n";
					}
				}
			}
		}

		$xml .= "</urlset>\n";

		$this->ensureDir(self::OUTPUT_DIR);
		file_put_contents(self::OUTPUT_DIR . '/sitemap.xml', $xml, LOCK_EX);
	}

	private function generateRobotsTxt(): void {
		$baseUrl = rtrim($this->settings['site_url'] ?? '', '/');
		$content = "User-agent: *\nAllow: /\n";
		if ($baseUrl !== '') {
			$sitemapUrl = $baseUrl . '/sitemap.xml';
			$content .= "Sitemap: {$sitemapUrl}\n";
		}
		$this->ensureDir(self::OUTPUT_DIR);
		file_put_contents(self::OUTPUT_DIR . '/robots.txt', $content, LOCK_EX);
	}

	/* ══════════════════════════════════════════════
	   タグページ生成
	   ══════════════════════════════════════════════ */

	private function buildTagPages(): int {
		if (!class_exists('CollectionEngine') || !CollectionEngine::isEnabled()) return 0;

		$built = 0;
		$collections = CollectionEngine::listCollections();

		foreach ($collections as $col) {
			$name = $col['name'];
			if ($name === 'pages') continue;

			$items = CollectionEngine::getItems($name);
			$tagMap = []; // tag => [items]
			foreach ($items as $slug => $item) {
				$tags = $item['meta']['tags'] ?? [];
				if (is_string($tags)) $tags = array_map('trim', explode(',', $tags));
				foreach ($tags as $tag) {
					$tag = trim($tag);
					if ($tag === '') continue;
					$tagSlug = mb_convert_case(str_replace(' ', '-', $tag), MB_CASE_LOWER, 'UTF-8');
					/* R3 fix: タグスラグのサニタイズ（パストラバーサル防止） */
					$tagSlug = preg_replace('/[^a-z0-9\-_\p{L}\p{N}]/u', '', $tagSlug);
					if ($tagSlug === '' || str_contains($tagSlug, '..')) continue;
					if (!isset($tagMap[$tagSlug])) {
						$tagMap[$tagSlug] = ['name' => $tag, 'items' => []];
					}
					$preview = strip_tags($item['html']);
					if (mb_strlen($preview, 'UTF-8') > 200) {
						$preview = mb_substr($preview, 0, 200, 'UTF-8') . '...';
					}
					$tagMap[$tagSlug]['items'][] = array_merge($item['meta'], [
						'slug'    => $slug,
						'preview' => $preview,
						'link'    => '/' . $name . '/' . $slug . '/',
					]);
				}
			}

			if (empty($tagMap)) continue;

			/* タグ別ページ */
			$tagTplPath = $this->themeDir . '/collection-' . $name . '-tag.html';
			$tagTpl = file_exists($tagTplPath) ? file_get_contents($tagTplPath) : null;

			$allTags = [];
			foreach ($tagMap as $tagSlug => $tagData) {
				$allTags[] = [
					'name'  => $tagData['name'],
					'slug'  => $tagSlug,
					'count' => count($tagData['items']),
					'url'   => '/' . $name . '/tag/' . $tagSlug . '/',
				];
			}

			foreach ($tagMap as $tagSlug => $tagData) {
				$pageSlug = $name . '/tag/' . $tagSlug;
				$dir = self::OUTPUT_DIR . '/' . $pageSlug;
				$this->ensureDir($dir);

				if ($tagTpl !== null) {
					$ctx = array_merge(
						ThemeEngine::buildStaticContext($pageSlug, '', $this->settings, ['title' => $tagData['name'] . ' - ' . ($col['label'] ?? $name)]),
						[
							'collection_name'  => $name,
							'collection_label' => $col['label'] ?? $name,
							'tag_name'         => $tagData['name'],
							'tag_slug'         => $tagSlug,
							'tag_items'        => $tagData['items'],
							'item_count'       => count($tagData['items']),
							'all_tags'         => $allTags,
						]
					);
					$html = TemplateEngine::render($tagTpl, $ctx, $this->themeDir);
				} else {
					$esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
					$html = '<h1>' . $esc($tagData['name']) . '</h1>' . "\n";
					$html .= '<p class="ap-tag-count">' . count($tagData['items']) . ' 件</p>' . "\n";
					$html .= '<div class="ap-collection-index">' . "\n";
					foreach ($tagData['items'] as $ti) {
						$html .= '<article class="ap-collection-entry">' . "\n";
						$html .= '  <h2><a href="' . $esc($ti['link']) . '">' . $esc($ti['title'] ?? $ti['slug']) . '</a></h2>' . "\n";
						if (!empty($ti['date'])) $html .= '  <time>' . $esc($ti['date']) . '</time>' . "\n";
						$html .= '  <p>' . $esc($ti['preview']) . '</p>' . "\n";
						$html .= '</article>' . "\n";
					}
					$html .= '</div>' . "\n";
				}

				$rendered = $this->renderPage($pageSlug, $html, ['title' => $tagData['name']]);
				if (!empty($this->settings['minify'] ?? true)) {
					$rendered = self::minifyHtml($rendered);
				}
				file_put_contents($dir . '/index.html', $rendered, LOCK_EX);
				$this->changedFiles[] = $dir . '/index.html';
				$built++;
			}

			/* タグ一覧ページ */
			$tagsIndexSlug = $name . '/tags';
			$tagsDir = self::OUTPUT_DIR . '/' . $tagsIndexSlug;
			$this->ensureDir($tagsDir);

			$esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
			$tagsHtml = '<h1>' . $esc($col['label'] ?? $name) . ' — タグ一覧</h1>' . "\n";
			$tagsHtml .= '<div class="ap-tags-list">' . "\n";
			usort($allTags, fn($a, $b) => $b['count'] - $a['count']);
			foreach ($allTags as $t) {
				$tagsHtml .= '<a href="' . $esc($t['url']) . '" class="ap-tag-badge">' . $esc($t['name']) . ' <span>(' . $t['count'] . ')</span></a> ' . "\n";
			}
			$tagsHtml .= '</div>' . "\n";

			$rendered = $this->renderPage($tagsIndexSlug, $tagsHtml, ['title' => ($col['label'] ?? $name) . ' タグ一覧']);
			if (!empty($this->settings['minify'] ?? true)) {
				$rendered = self::minifyHtml($rendered);
			}
			file_put_contents($tagsDir . '/index.html', $rendered, LOCK_EX);
			$this->changedFiles[] = $tagsDir . '/index.html';
			$built++;
		}
		return $built;
	}

	private function collectTags(array $items): array {
		$tags = [];
		foreach ($items as $item) {
			$itemTags = $item['tags'] ?? [];
			if (is_string($itemTags)) $itemTags = array_map('trim', explode(',', $itemTags));
			foreach ($itemTags as $tag) {
				$tag = trim($tag);
				if ($tag === '') continue;
				$tagSlug = mb_convert_case(str_replace(' ', '-', $tag), MB_CASE_LOWER, 'UTF-8');
				$tagSlug = preg_replace('/[^a-z0-9\-_\p{L}\p{N}]/u', '', $tagSlug);
				if ($tagSlug === '' || str_contains($tagSlug, '..')) continue;
				if (!isset($tags[$tagSlug])) {
					$tags[$tagSlug] = ['name' => $tag, 'slug' => $tagSlug, 'count' => 0];
				}
				$tags[$tagSlug]['count']++;
			}
		}
		return array_values($tags);
	}

	/* ══════════════════════════════════════════════
	   404 エラーページ
	   ══════════════════════════════════════════════ */

	private function generate404Page(): void {
		$customTpl = $this->themeDir . '/404.html';
		if (file_exists($customTpl)) {
			$tplContent = file_get_contents($customTpl);
			if ($tplContent !== false) {
				$ctx = ThemeEngine::buildStaticContext('404', '', $this->settings, ['title' => 'ページが見つかりません']);
				$html = TemplateEngine::render($tplContent, $ctx, $this->themeDir);
			} else {
				$html = $this->renderPage('404', '<h1>404 — ページが見つかりません</h1><p>お探しのページは存在しないか、移動されました。</p>', ['title' => '404']);
			}
		} else {
			$html = $this->renderPage('404', '<h1>404 — ページが見つかりません</h1><p>お探しのページは存在しないか、移動されました。</p><p><a href="/">トップページへ</a></p>', ['title' => '404']);
		}

		if (!empty($this->settings['minify'] ?? true)) {
			$html = self::minifyHtml($html);
		}
		$this->ensureDir(self::OUTPUT_DIR);
		file_put_contents(self::OUTPUT_DIR . '/404.html', $html, LOCK_EX);
		$this->changedFiles[] = self::OUTPUT_DIR . '/404.html';
	}

	/* ══════════════════════════════════════════════
	   クライアントサイド検索インデックス
	   ══════════════════════════════════════════════ */

	private function generateSearchIndex(): void {
		$index = [];

		foreach ($this->pages as $slug => $content) {
			if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
			$text = strip_tags($content);
			$text = preg_replace('/\s+/', ' ', trim($text));

			$entry = [
				'slug'  => $slug,
				'title' => $slug,
				'body'  => mb_substr($text, 0, 500, 'UTF-8'),
				'url'   => '/' . ($slug === 'index' ? '' : $slug . '/'),
			];

			/* コレクションアイテムならメタ情報を追加 */
			if (str_contains($slug, '/') && class_exists('CollectionEngine')) {
				$parts = explode('/', $slug, 2);
				$item = CollectionEngine::getItem($parts[0], $parts[1]);
				if ($item) {
					/* R9 fix: ドラフト・非公開アイテムを検索インデックスから除外 */
					$status = $item['meta']['status'] ?? 'published';
					if (!empty($item['meta']['draft']) || $status === 'draft' || $status === 'archived') {
						continue;
					}
					$entry['title'] = $item['meta']['title'] ?? $parts[1];
					$entry['tags']  = $item['meta']['tags'] ?? [];
					$entry['date']  = $item['meta']['date'] ?? '';
				}
			}
			$index[] = $entry;
		}

		$this->ensureDir(self::OUTPUT_DIR);
		file_put_contents(
			self::OUTPUT_DIR . '/search-index.json',
			json_encode($index, JSON_UNESCAPED_UNICODE),
			LOCK_EX
		);
		$this->changedFiles[] = self::OUTPUT_DIR . '/search-index.json';

		/* ap-search.js をアセットにコピー */
		$searchJs = 'engines/JsEngine/ap-search.js';
		if (file_exists($searchJs)) {
			$dst = self::OUTPUT_DIR . '/assets/ap-search.js';
			$this->ensureDir(dirname($dst));
			copy($searchJs, $dst);
		}
	}

	/* ══════════════════════════════════════════════
	   HTML / CSS ミニファイ
	   ══════════════════════════════════════════════ */

	private static function minifyHtml(string $html): string {
		/* <pre>, <code>, <script>, <style>, <textarea> 内は保護 */
		$protected = [];
		/* R6 fix: 後方参照で開閉タグの一致を保証（ネスト保護の正確性向上） */
		$html = preg_replace_callback(
			'#(<(pre|code|script|style|textarea)\b[^>]*>)(.*?)(</\2>)#si',
			function($m) use (&$protected) {
				/* C1 fix: HTML コメント形式ではなく固有トークンを使用（コメント除去で消されない） */
				$key = "\x00AP_PROTECT_" . count($protected) . "\x00";
				$protected[$key] = $m[0];
				return $key;
			},
			$html
		) ?? $html;

		/* HTML コメント除去（IE条件コメント以外） */
		$html = preg_replace('/<!--(?!\[if\s).*?-->/s', '', $html) ?? $html;
		/* 連続空白を圧縮 */
		$html = preg_replace('/\s{2,}/', ' ', $html) ?? $html;
		/* タグ間の空白を除去 */
		$html = preg_replace('/>\s+</', '><', $html) ?? $html;

		/* 保護領域を復元 */
		foreach ($protected as $key => $val) {
			$html = str_replace($key, $val, $html);
		}
		return trim($html);
	}

	public static function minifyCss(string $css): string {
		/* R7 fix: calc() 内の空白を保護 */
		$calcProtected = [];
		$css = preg_replace_callback('/calc\([^)]+\)/i', function($m) use (&$calcProtected) {
			$key = "\x00AP_CALC_" . count($calcProtected) . "\x00";
			$calcProtected[$key] = $m[0];
			return $key;
		}, $css) ?? $css;
		/* コメント除去 */
		$css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
		/* 空白圧縮 */
		$css = preg_replace('/\s+/', ' ', $css) ?? $css;
		/* セレクタ周辺の空白除去 */
		$css = preg_replace('/\s*([{};:,>~])\s*/', '$1', $css) ?? $css;
		/* 末尾セミコロン除去 */
		$css = str_replace(';}', '}', $css);
		/* calc() 復元 */
		foreach ($calcProtected as $key => $val) {
			$css = str_replace($key, $val, $css);
		}
		return trim($css);
	}

	/* ══════════════════════════════════════════════
	   リダイレクト管理
	   ══════════════════════════════════════════════ */

	private function generateRedirects(): void {
		$redirects = json_read('redirects.json', settings_dir());
		if (empty($redirects)) return;

		/* _redirects ファイル（Netlify / Cloudflare Pages 互換） */
		$lines = [];
		foreach ($redirects as $r) {
			$from = $r['from'] ?? '';
			$to   = $r['to'] ?? '';
			$code = (int)($r['code'] ?? 301);
			if ($from === '' || $to === '') continue;
			if (!in_array($code, [301, 302], true)) $code = 301;
			$from = preg_replace('/[\r\n\x00-\x1f]/', '', $from);
			$to   = preg_replace('/[\r\n\x00-\x1f]/', '', $to);
			if (!str_starts_with($from, '/')) continue;
			$lines[] = "{$from}  {$to}  {$code}";
		}

		$this->ensureDir(self::OUTPUT_DIR);
		if ($lines) {
			file_put_contents(self::OUTPUT_DIR . '/_redirects', implode("\n", $lines) . "\n", LOCK_EX);
			$this->changedFiles[] = self::OUTPUT_DIR . '/_redirects';
		}
	}

	/* ══════════════════════════════════════════════
	   ビルドフック
	   ══════════════════════════════════════════════ */

	private function runHook(string $hookName, array $context = []): void {
		$hooks = json_read('build_hooks.json', settings_dir());
		if (empty($hooks[$hookName])) return;

		foreach ((array)$hooks[$hookName] as $hookFile) {
			if (!is_string($hookFile)) continue;
			/* セキュリティ: engines/ または data/ 内のファイルのみ許可 */
			$real = realpath($hookFile);
			if ($real === false) continue;
			$projectRoot = realpath('.') ?: '';
			if (!str_starts_with($real, $projectRoot . '/engines/') && !str_starts_with($real, $projectRoot . '/data/')) {
				$this->warnings[] = "フック拒否（許可外パス）: {$hookFile}";
				continue;
			}
			if (!str_ends_with($real, '.php')) continue;

			try {
				/* C4 fix: array_merge は参照を破壊するため直接代入 */
				$apHookContext = $context;
				$apHookContext['settings']   = $this->settings;
				$apHookContext['pages']      = array_keys($this->pages);
				$apHookContext['output_dir'] = self::OUTPUT_DIR;
				$apHookContext['theme_dir']  = $this->themeDir;
				include $real;
			} catch (\Throwable $e) {
				$this->warnings[] = "フックエラー ({$hookFile}): " . $e->getMessage();
			}
		}
	}

	/* ══════════════════════════════════════════════
	   インクリメンタルデプロイ
	   ══════════════════════════════════════════════ */

	private function serveDiffZip(): never {
		$changedFiles = $this->buildState['changed_files'] ?? [];
		if (empty($changedFiles)) {
			http_response_code(400);
			echo json_encode(['error' => '変更ファイルがありません。先にビルドを実行してください。']);
			exit;
		}
		if (!class_exists('ZipArchive')) {
			http_response_code(500);
			echo json_encode(['error' => 'ZipArchive 拡張が利用できません。']);
			exit;
		}

		$tmpFile = sys_get_temp_dir() . '/ap_deploy_diff_' . date('Ymd_His') . '.zip';
		$zip = new ZipArchive();
		if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			http_response_code(500);
			echo json_encode(['error' => 'ZIP ファイルの作成に失敗しました。']);
			exit;
		}

		$prefix = self::OUTPUT_DIR . '/';
		foreach ($changedFiles as $file) {
			if (file_exists($file) && str_starts_with($file, $prefix)) {
				$rel = substr($file, strlen($prefix));
				$zip->addFile($file, $rel);
			}
		}
		$zip->close();

		try {
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="deploy-diff-' . date('Ymd') . '.zip"');
			header('Content-Length: ' . filesize($tmpFile));
			readfile($tmpFile);
		} finally {
			@unlink($tmpFile);
		}
		exit;
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
		/* M2 fix: コレクションディレクトリは孤立扱いしない */
		$collectionNames = [];
		if (class_exists('CollectionEngine') && CollectionEngine::isEnabled()) {
			foreach (CollectionEngine::listCollections() as $col) {
				$collectionNames[$col['name']] = true;
			}
		}
		$dirs = glob(self::OUTPUT_DIR . '/*', GLOB_ONLYDIR) ?: [];
		foreach ($dirs as $dir) {
			$slug = basename($dir);
			if ($slug === 'assets') continue;
			if (isset($collectionNames[$slug])) continue;
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
		$content = "Options -Indexes -ExecCGI\n\n"
			. "# PHP 実行禁止\n"
			. "<FilesMatch \"\\.php$\">\n"
			. "    Require all denied\n"
			. "</FilesMatch>\n\n";

		/* リダイレクトルール（C2 fix: 改行・制御文字を除去してインジェクション防止） */
		$redirects = json_read('redirects.json', settings_dir());
		if (!empty($redirects)) {
			$content .= "# リダイレクト\n";
			foreach ($redirects as $r) {
				$from = $r['from'] ?? '';
				$to   = $r['to'] ?? '';
				$code = (int)($r['code'] ?? 301);
				if ($from === '' || $to === '') continue;
				if (!in_array($code, [301, 302], true)) $code = 301;
				/* 改行・制御文字を除去（.htaccess インジェクション防止） */
				$from = preg_replace('/[\r\n\x00-\x1f]/', '', $from);
				$to   = preg_replace('/[\r\n\x00-\x1f]/', '', $to);
				/* パスとして妥当か検証 */
				if (!str_starts_with($from, '/')) continue;
				$content .= "Redirect {$code} {$from} {$to}\n";
			}
			$content .= "\n";
		}

		/* 404 エラーページ */
		$content .= "ErrorDocument 404 /404.html\n";

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
