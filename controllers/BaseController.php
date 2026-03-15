<?php
/**
 * BaseController - Controller 基底クラス
 *
 * 全 Controller が共有するヘルパーメソッドを提供する。
 *
 * @since Ver.1.7-36
 * @license Adlaire License Ver.2.0
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

abstract class BaseController {

	/** JSON 成功レスポンス */
	protected function ok(mixed $data = null): Response {
		$body = $data !== null ? ['ok' => true, 'data' => $data] : ['ok' => true];
		return Response::json($body);
	}

	/** JSON エラーレスポンス */
	protected function error(string $message, int $status = 400): Response {
		return Response::json(['ok' => false, 'error' => $message], $status);
	}

	/** ロール検証 */
	protected function requireRole(string $role): ?Response {
		if (!\ACE\Admin\AdminManager::hasRole($role)) {
			return $this->error(\AIS\Core\I18n::t('auth.no_edit_permission'), 403);
		}
		return null;
	}

	/** POST パラメータバリデーション（正規表現） */
	protected function validateParam(Request $request, string $key, string $pattern, string $errorMsg = 'Invalid parameter'): ?Response {
		$value = $request->post($key, '');
		if (!preg_match($pattern, $value)) {
			return $this->error($errorMsg, 400);
		}
		return null;
	}
}
