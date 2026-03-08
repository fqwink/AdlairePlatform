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

		$htmlPath = self::THEMES_DIR . '/' . $themeSelect . '/theme.html';
		$phpPath  = self::THEMES_DIR . '/' . $themeSelect . '/theme.php';

		if (file_exists($htmlPath)) {
			$context = self::buildContext();
			echo TemplateEngine::render(file_get_contents($htmlPath), $context);
		} elseif (file_exists($phpPath)) {
			require $phpPath;
		} else {
			$fallbackHtml = self::THEMES_DIR . '/' . self::FALLBACK . '/theme.html';
			$fallbackPhp  = self::THEMES_DIR . '/' . self::FALLBACK . '/theme.php';
			if (file_exists($fallbackHtml)) {
				echo TemplateEngine::render(file_get_contents($fallbackHtml), self::buildContext());
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

		return [
			'title'          => $c['title'] ?? '',
			'page'           => $c['page'] ?? '',
			'host'           => $host ?? '',
			'themeSelect'    => $c['themeSelect'] ?? 'AP-Default',
			'description'    => $c['description'] ?? '',
			'keywords'       => $c['keywords'] ?? '',
			'admin'          => $admin,
			'csrf_token'     => csrf_token(),
			'admin_scripts'  => $adminScripts,
			'settings_panel' => $admin ? self::renderSettingsPanel() : '',
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
			'settings_panel' => '',
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
	 * settings() の文字列返却版
	 */
	private static function renderSettingsPanel(): string {
		global $c, $d;
		$html = '';
		if (!empty($c['migrate_warning'])) {
			$html .= "<div style='background:#c0392b;color:#fff;padding:10px;margin:5px 0;font-weight:bold;'>警告: パスワードが MD5 から bcrypt に移行されました。パスワードが \"admin\" にリセットされています。すぐに変更してください。</div>";
		}
		$html .= "<div class='settings'>
	<h3 class='toggle'>↕ Settings ↕</h3>
	<div class='hide'>
	<div class='change border'><b>Theme</b>&nbsp;<span id='themeSelect'><select name='themeSelect' id='ap-theme-select'>";
		foreach (self::listThemes() as $val) {
			$select = ($val == $c['themeSelect']) ? ' selected' : '';
			$html .= '<option value="' . h($val) . '"' . $select . '>' . h($val) . "</option>\n";
		}
		$html .= "</select></span></div>
	<div class='change border'><b>Menu <small>(add a page below and <a href='./' id='ap-refresh-link'>refresh</a>)</small></b><span id='menu' title='Home' class='editText'>" . $c['menu'] . "</span></div>";
		foreach (['title', 'description', 'keywords', 'copyright'] as $key) {
			$html .= "<div class='change border'><span title='" . h($d['default'][$key] ?? '') . "' id='" . h($key) . "' class='editText'>" . $c[$key] . "</span></div>";
		}
		$html .= "</div></div>";
		$html .= "<div class='settings'>
	<h3 class='toggle'>↕ アップデート ↕</h3>
	<div class='hide'>
	<div class='change border'>
		<b>現在のバージョン:</b> " . h(AP_VERSION) . "
		<br><br>
		<button id='ap-check-update' style='cursor:pointer;'>更新を確認</button>
		<span id='ap-update-status' style='margin-left:10px;'></span>
		<div id='ap-update-result' style='margin-top:10px;'></div>
	</div>
	</div></div>";
		return $html;
	}
}
