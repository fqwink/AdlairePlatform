<?php
/**
 * GitEngine - 後方互換シム
 *
 * Ver.1.8: 全ロジックを AIS\Deployment\GitService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \AIS\Deployment\GitService を使用してください
 */
class GitEngine {

	/** @deprecated */
	public static function isEnabled(): bool {
		return \AIS\Deployment\GitService::isEnabled();
	}

	/** @deprecated */
	public static function loadConfig(): array {
		return \AIS\Deployment\GitService::loadConfig();
	}

	/** @deprecated */
	public static function saveConfig(array $config): void {
		\AIS\Deployment\GitService::saveConfig($config);
	}

	/** @deprecated */
	public static function hasGitCommand(): bool {
		return \AIS\Deployment\GitService::hasGitCommand();
	}

	/** @deprecated */
	public static function testConnection(): array {
		return \AIS\Deployment\GitService::testConnection();
	}

	/** @deprecated */
	public static function pull(): array {
		return \AIS\Deployment\GitService::pull();
	}

	/** @deprecated */
	public static function push(string $commitMessage = ''): array {
		return \AIS\Deployment\GitService::push($commitMessage);
	}

	/** @deprecated */
	public static function log(int $limit = 20): array {
		return \AIS\Deployment\GitService::log($limit);
	}

	/** @deprecated */
	public static function status(): array {
		return \AIS\Deployment\GitService::status();
	}

	/** @deprecated */
	public static function createPreviewBranch(string $name): array {
		return \AIS\Deployment\GitService::createPreviewBranch($name);
	}

	/** @deprecated */
	public static function createIssue(string $title, string $body, array $labels = []): array {
		return \AIS\Deployment\GitService::createIssue($title, $body, $labels);
	}
}
