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
		global $c, $d, $host, $lstatus, $apcredit, $hook;

		$menuItems = self::parseMenu($c['menu'] ?? '', $c['page'] ?? '');
		$admin     = is_loggedin();

		$adminScripts = '';
		if ($admin) {
			foreach (($hook['admin-head'] ?? []) as $o) {
				$adminScripts .= "\t" . $o . "\n";
			}
		}

		$contentHtml = self::renderContent($c['page'] ?? '', $c['content'] ?? '', $admin);
		$subsideHtml = self::renderContent('subside', $c['subside'] ?? '', $admin);

		$ctx = [
			'title'          => $c['title'] ?? '',
			'page'           => $c['page'] ?? '',
			'host'           => $host ?? '',
			'themeSelect'    => $c['themeSelect'] ?? 'AP-Default',
			'description'    => $c['description'] ?? '',
			'keywords'       => $c['keywords'] ?? '',
			'admin'          => $admin,
			'csrf_token'     => csrf_token(),
			'admin_scripts'  => $adminScripts,
			'content'        => $contentHtml,
			'subside'        => $subsideHtml,
			'copyright'      => $c['copyright'] ?? '',
			'login_status'   => $lstatus ?? '',
			'credit'         => $apcredit ?? '',
			'menu_items'     => $menuItems,
		];

		if ($admin) {
			$ctx = array_merge($ctx, self::buildSettingsContext());
		}

		return $ctx;
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

	/**
	 * content() の文字列返却版
	 */
	private static function renderContent(string $id, string $content, bool $admin): string {
		global $d;
		$content = (string)($content ?? '');
		if ($admin) {
			return "<span title='" . h($d['default']['content'] ?? 'Click to edit!')
				. "' id='" . h($id) . "' class='editRich'>" . $content . "</span>";
		}
		return $content;
	}

	/**
	 * settings.html パーシャル用のコンテキスト変数を構築
	 */
	private static function buildSettingsContext(): array {
		global $c, $d;

		$selectHtml = "<select name='themeSelect' id='ap-theme-select'>";
		foreach (self::listThemes() as $val) {
			$selected = ($val == $c['themeSelect']) ? ' selected' : '';
			$selectHtml .= '<option value="' . h($val) . '"' . $selected . '>' . h($val) . "</option>\n";
		}
		$selectHtml .= '</select>';

		$fields = [];
		foreach (['title', 'description', 'keywords', 'copyright'] as $key) {
			$fields[] = [
				'key'           => $key,
				'default_value' => $d['default'][$key] ?? '',
				'value'         => $c[$key] ?? '',
			];
		}

		return [
			'migrate_warning'  => !empty($c['migrate_warning']),
			'theme_select_html' => $selectHtml,
			'menu_raw'         => $c['menu'] ?? '',
			'settings_fields'  => $fields,
			'ap_version'       => AP_VERSION,
		];
	}
}
