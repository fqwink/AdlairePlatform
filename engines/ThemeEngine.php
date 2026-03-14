<?php
/**
 * ThemeEngine - テーマ検証・読み込み・コンテキスト構築
 *
 * theme.html（テンプレートエンジン方式）でレンダリング。
 * theme.php（レガシー PHP 方式）は Ver.1.3-28 で廃止。
 */
class ThemeEngine {
	private const FALLBACK   = 'AP-Default';
	private const THEMES_DIR = 'themes';

	/**
	 * テーマをロードしてレンダリング
	 * theme.html を TemplateEngine で処理。なければ AP-Default にフォールバック
	 */
	public static function load(string $themeSelect): void {
		if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeSelect)) {
			$themeSelect = self::FALLBACK;
		}

		$themeDir = self::THEMES_DIR . '/' . $themeSelect;
		$htmlPath = $themeDir . '/theme.html';

		if (!file_exists($htmlPath)) {
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', 'テーマフォールバック', ['requested' => $themeSelect, 'fallback' => self::FALLBACK, 'missing' => $htmlPath]);
			$themeDir = self::THEMES_DIR . '/' . self::FALLBACK;
			$htmlPath = $themeDir . '/theme.html';
		}

		$tpl = FileSystem::read($htmlPath);
		if ($tpl !== false) {
			echo TemplateEngine::render($tpl, self::buildContext(), $themeDir);
		} else {
			echo '<!-- ThemeEngine: テンプレート読み込みエラー -->';
		}
	}

	public static function listThemes(): array {
		$dirs = glob(self::THEMES_DIR . '/*', GLOB_ONLYDIR);
		return is_array($dirs) ? array_map('basename', $dirs) : [];
	}

	/**
	 * 動的 CMS 用のテンプレートコンテキストを構築
	 */
	public static function buildContext(): array {
		/* B-3 fix: AppContext 経由でアクセス（global 変数への直接依存を解消） */
		$menuItems = self::parseMenu(
			AppContext::config('menu', ''),
			AppContext::config('page', '')
		);
		$admin = AdminEngine::isLoggedIn();

		$adminScripts = '';
		if ($admin) {
			$adminScripts = AdminEngine::getAdminScripts();
		}

		$contentHtml = AdminEngine::renderEditableContent(
			AppContext::config('page', ''),
			AppContext::config('content', ''),
			AppContext::defaults('default', 'content', 'Click to edit!')
		);
		$subsideHtml = AdminEngine::renderEditableContent(
			'subside',
			AppContext::config('subside', ''),
			AppContext::defaults('default', 'content', 'Click to edit!')
		);

		return [
			'title'          => AppContext::config('title', ''),
			'page'           => AppContext::config('page', ''),
			'host'           => AppContext::host(),
			'themeSelect'    => AppContext::config('themeSelect', 'AP-Default'),
			'description'    => AppContext::config('description', ''),
			'keywords'       => AppContext::config('keywords', ''),
			'admin'          => $admin,
			'csrf_token'     => AdminEngine::csrfToken(),
			'admin_scripts'  => $adminScripts,
			'content'        => $contentHtml,
			'subside'        => $subsideHtml,
			'copyright'      => AppContext::config('copyright', ''),
			'login_status'   => AppContext::loginStatus(),
			'credit'         => AppContext::credit(),
			'menu_items'     => $menuItems,
		];
	}

	/**
	 * StaticEngine 用のコンテキスト（管理者 UI なし）
	 * OGP / JSON-LD / canonical 自動生成を含む
	 */
	public static function buildStaticContext(
		string $slug, string $content, array $settings, array $meta = []
	): array {
		$menuItems = self::parseMenu($settings['menu'] ?? '', $slug);

		$siteTitle   = $settings['title'] ?? '';
		$description = $settings['description'] ?? '';
		$baseUrl     = rtrim($settings['site_url'] ?? '', '/');

		/* ページ固有の値（コレクションアイテムの meta から） */
		$pageTitle = $meta['title'] ?? $slug;
		$pageDesc  = $meta['description'] ?? '';
		if ($pageDesc === '' && $content !== '') {
			$pageDesc = mb_substr(strip_tags($content), 0, 160, 'UTF-8');
			$pageDesc = preg_replace('/\s+/', ' ', trim($pageDesc)) ?? '';
		}
		if ($pageDesc === '' || $pageDesc === null) $pageDesc = $description;
		$pageImage = $meta['thumbnail'] ?? $meta['image'] ?? $meta['og_image'] ?? '';
		$pageDate  = $meta['date'] ?? $meta['publishDate'] ?? '';
		$pageTags  = $meta['tags'] ?? [];
		if (is_string($pageTags)) $pageTags = array_map('trim', explode(',', $pageTags));

		$canonicalUrl = $baseUrl . '/' . ($slug === 'index' ? '' : $slug . '/');
		$ogType = str_contains($slug, '/') ? 'article' : 'website';

		/* OGP メタタグ HTML 一括生成 */
		$esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
		$ogMeta  = '<meta property="og:title" content="' . $esc($pageTitle) . '">' . "\n";
		$ogMeta .= '<meta property="og:description" content="' . $esc($pageDesc) . '">' . "\n";
		$ogMeta .= '<meta property="og:type" content="' . $ogType . '">' . "\n";
		$ogMeta .= '<meta property="og:site_name" content="' . $esc($siteTitle) . '">' . "\n";
		if ($baseUrl !== '') {
			$ogMeta .= '<meta property="og:url" content="' . $esc($canonicalUrl) . '">' . "\n";
			$ogMeta .= '<link rel="canonical" href="' . $esc($canonicalUrl) . '">' . "\n";
		}
		if ($pageImage !== '') {
			$imgUrl = str_starts_with($pageImage, 'http') ? $pageImage : $baseUrl . '/' . ltrim($pageImage, '/');
			$ogMeta .= '<meta property="og:image" content="' . $esc($imgUrl) . '">' . "\n";
		}
		/* Twitter Card */
		$ogMeta .= '<meta name="twitter:card" content="' . ($pageImage ? 'summary_large_image' : 'summary') . '">' . "\n";
		$ogMeta .= '<meta name="twitter:title" content="' . $esc($pageTitle) . '">' . "\n";
		$ogMeta .= '<meta name="twitter:description" content="' . $esc($pageDesc) . '">' . "\n";

		/* JSON-LD 構造化データ */
		$jsonLd = '';
		if ($baseUrl !== '') {
			if ($ogType === 'article') {
				$ld = [
					'@context' => 'https://schema.org',
					'@type'    => 'Article',
					'headline' => $pageTitle,
					'description' => $pageDesc,
					'url'      => $canonicalUrl,
				];
				if ($pageDate !== '') $ld['datePublished'] = $pageDate;
				if ($pageImage !== '') {
					$ld['image'] = str_starts_with($pageImage, 'http') ? $pageImage : $baseUrl . '/' . ltrim($pageImage, '/');
				}
				if ($pageTags) $ld['keywords'] = implode(', ', $pageTags);
			} else {
				$ld = [
					'@context' => 'https://schema.org',
					'@type'    => 'WebPage',
					'name'     => $pageTitle,
					'description' => $pageDesc,
					'url'      => $canonicalUrl,
				];
			}
			/* R13 fix: JSON_UNESCAPED_SLASHES を除去（</script> インジェクション防止） */
		$jsonLd = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE) . '</script>';
		}

		/* パンくずリスト JSON-LD */
		$breadcrumbLd = '';
		if ($baseUrl !== '' && $slug !== 'index') {
			$crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => $siteTitle ?: 'Home', 'item' => $baseUrl . '/']];
			$parts = explode('/', $slug);
			$path = '';
			$pos = 2;
			$lastIndex = count($parts) - 1;
			foreach ($parts as $i => $part) {
				$path .= $part . '/';
				/* M3 fix: meta title は最後のセグメントのみに使用 */
				$name = ($i === $lastIndex) ? ($meta['title'] ?? $part) : $part;
				$crumbs[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $name, 'item' => $baseUrl . '/' . $path];
			}
			/* R13 fix: JSON_UNESCAPED_SLASHES を除去（</script> インジェクション防止） */
			$breadcrumbLd = '<script type="application/ld+json">' . json_encode([
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $crumbs,
			], JSON_UNESCAPED_UNICODE) . '</script>';
		}

		return [
			'title'          => $siteTitle,
			'page'           => $slug,
			'page_title'     => $pageTitle,
			'host'           => '/',
			'themeSelect'    => $settings['themeSelect'] ?? 'AP-Default',
			'description'    => $description,
			'keywords'       => $settings['keywords'] ?? '',
			'admin'          => false,
			'csrf_token'     => '',
			'admin_scripts'  => '',
			'content'        => $content,
			'subside'        => $settings['subside'] ?? '',
			'copyright'      => $settings['copyright'] ?? '',
			'login_status'   => '',
			'credit'         => "Powered by <a href=''>Adlaire Platform</a>",
			'menu_items'     => $menuItems,
			/* OGP / SEO */
			'og_meta_tags'   => $ogMeta,
			'json_ld'        => $jsonLd . ($breadcrumbLd ? "\n" . $breadcrumbLd : ''),
			'canonical_url'  => $canonicalUrl,
			'og_title'       => $pageTitle,
			'og_description' => $pageDesc,
			'og_image'       => $pageImage,
			'og_type'        => $ogType,
			'search_enabled' => !empty($settings['search_enabled']),
		];
	}

	/**
	 * メニュー文字列を構造化配列にパース
	 */
	public static function parseMenu(string $menuStr, string $currentPage): array {
		$items = [];
		$mlist = explode("<br />\n", $menuStr);
		foreach ($mlist as $cp) {
			$label = trim(strip_tags($cp));
			if ($label === '') continue;
			$slug = getSlug($label);
			$items[] = [
				'slug'   => $slug,
				'label'  => $label,
				'active' => ($currentPage === $slug),
			];
		}
		return $items;
	}
}
