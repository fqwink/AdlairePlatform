<?php
/**
 * UpdateEngine - 後方互換シム
 *
 * Ver.1.8: 全ロジックを AIS\Deployment\UpdateService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \AIS\Deployment\UpdateService を使用してください
 */
class UpdateEngine {

	/** @deprecated */
	public static function checkEnvironment(): array {
		return \AIS\Deployment\UpdateService::checkEnvironment();
	}

	/** @deprecated */
	public static function checkUpdate(): array {
		return \AIS\Deployment\UpdateService::checkUpdate();
	}

	/** @deprecated */
	public static function executeApplyUpdate(): array {
		return \AIS\Deployment\UpdateService::executeApplyUpdate();
	}

	/** @deprecated */
	public static function executeRollback(string $name): array {
		return \AIS\Deployment\UpdateService::executeRollback($name);
	}

	/** @deprecated */
	public static function executeDeleteBackup(string $name): array {
		return \AIS\Deployment\UpdateService::executeDeleteBackup($name);
	}
}
