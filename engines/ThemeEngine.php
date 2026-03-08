<?php
/**
 * ThemeEngine - テーマ検証・読み込み・コンテキスト構築
 *
 * theme.html（テンプレートエンジン方式）を優先し、
 * なければ theme.php（レガシー PHP 方式）にフォールバック。
 */
class ThemeEngine {
	private const FALLBACK   = 'AP-Default';
	private const THEMES_DIR = 'themes';

	/**
	 * テーマをロードしてレンダリング
	 * theme.html があれば TemplateEngine で処理、なければ theme.php を require
	 */
	public static function load(string $themeSelect): void {
		if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeSelect)) {
			$themeSelect = self::FALLBACK;
		}

		$themeDir  = self::THEMES_DIR . '/' . $themeSelect;
		$htmlPath  = $themeDir . '/theme.html';
		$phpPath   = $themeDir . '/theme.php';

		if (file_exists($htmlPath)) {
			$tpl = file_get_contents($htmlPath);
			if ($tpl !== false) {
				$context = self::buildContext();
				echo TemplateEngine::render($tpl, $context, $themeDir);
			} elseif (file_exists($phpPath)) {
				require $phpPath;
			}
		} elseif (file_exists($phpPath)) {
			require $phpPath;
		} else {
			$fallbackDir  = self::THEMES_DIR . '/' . self::FALLBACK;
			$fallbackHtml = $fallbackDir . '/theme.html';
			$fallbackPhp  = $fallbackDir . '/theme.php';
			$tpl = file_exists($fallbackHtml) ? file_get_contents($fallbackHtml) : false;
			if ($tpl !== false) {
				echo TemplateEngine::render($tpl, self::buildContext(), $fallbackDir);
			} else {
				require $fallbackPhp;
			}
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
		global $c, $d, $host, $lstatus, $apcredit;

		$menuItems = self::parseMenu($c['menu'] ?? '', $c['page'] ?? '');
		$admin     = AdminEngine::isLoggedIn();

		$adminScripts = '';
		if ($admin) {
			$adminScripts = AdminEngine::getAdminScripts();
		}

		$contentHtml = AdminEngine::renderEditableContent(
			$c['page'] ?? '',
			$c['content'] ?? '',
			$d['default']['content'] ?? 'Click to edit!'
		);
		$subsideHtml = AdminEngine::renderEditableContent(
			'subside',
			$c['subside'] ?? '',
			$d['default']['content'] ?? 'Click to edit!'
		);

		return [
			'title'          => $c['title'] ?? '',
			'page'           => $c['page'] ?? '',
			'host'           => $host ?? '',
			'themeSelect'    => $c['themeSelect'] ?? 'AP-Default',
			'description'    => $c['description'] ?? '',
			'keywords'       => $c['keywords'] ?? '',
			'admin'          => $admin,
			'csrf_token'     => AdminEngine::csrfToken(),
			'admin_scripts'  => $adminScripts,
			'content'        => $contentHtml,
			'subside'        => $subsideHtml,
			'copyright'      => $c['copyright'] ?? '',
			'login_status'   => $lstatus ?? '',
			'credit'         => $apcredit ?? '',
			'menu_items'     => $menuItems,
		];
	}

	/**
	 * StaticEngine 用のコンテキスト（管理者 UI なし）
	 */
	public static function buildStaticContext(
		string $slug, string $content, array $settings
	): array {
		$menuItems = self::parseMenu($settings['menu'] ?? '', $slug);

		return [
			'title'          => $settings['title'] ?? '',
			'page'           => $slug,
			'host'           => '/',
			'themeSelect'    => $settings['themeSelect'] ?? 'AP-Default',
			'description'    => $settings['description'] ?? '',
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
