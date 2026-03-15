<?php
/**
 * DiagnosticEngine - リアルタイム診断・テレメトリエンジン（後方互換シム）
 *
 * Ver.1.8: 全ロジックを AIS\System\DiagnosticsManager に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — AIS\System\DiagnosticsManager を使用してください
 */
class DiagnosticEngine {
	use EngineTrait;

	/** @var \AIS\System\DiagnosticsCollector|null Ver.1.5 Framework 診断コレクター */
	private static ?\AIS\System\DiagnosticsCollector $collector = null;

	/**
	 * Ver.1.5: Framework DiagnosticsCollector インスタンスを取得する
	 */
	public static function getCollector(): \AIS\System\DiagnosticsCollector {
		if (self::$collector === null) {
			self::$collector = new \AIS\System\DiagnosticsCollector('data/diagnostics');
			if (self::isEnabled()) {
				self::$collector->enable();
			}
		}
		return self::$collector;
	}

	/* ── 設定管理 ── */

	public static function loadConfig(): array {
		return \AIS\System\DiagnosticsManager::loadConfig();
	}

	public static function saveConfig(array $config): void {
		\AIS\System\DiagnosticsManager::saveConfig($config);
	}

	public static function isEnabled(): bool {
		return \AIS\System\DiagnosticsManager::isEnabled();
	}

	public static function getLevel(): string {
		return \AIS\System\DiagnosticsManager::getLevel();
	}

	public static function setEnabled(bool $enabled): void {
		\AIS\System\DiagnosticsManager::setEnabled($enabled);
	}

	public static function setLevel(string $level): void {
		\AIS\System\DiagnosticsManager::setLevel($level);
	}

	public static function getInstallId(): string {
		return \AIS\System\DiagnosticsManager::getInstallId();
	}

	/* ── パフォーマンスプロファイラ ── */

	public static function startTimer(string $label): void {
		\AIS\System\DiagnosticsManager::startTimer($label);
	}

	public static function stopTimer(string $label): void {
		\AIS\System\DiagnosticsManager::stopTimer($label);
	}

	public static function getTimings(): array {
		return \AIS\System\DiagnosticsManager::getTimings();
	}

	/* ── エンジン別実行時間トラッカー ── */

	public static function startEngineTimer(string $engine, string $method = ''): void {
		\AIS\System\DiagnosticsManager::startEngineTimer($engine, $method);
	}

	public static function stopEngineTimer(string $engine, string $method = ''): ?float {
		return \AIS\System\DiagnosticsManager::stopEngineTimer($engine, $method);
	}

	public static function getEngineTimings(): array {
		return \AIS\System\DiagnosticsManager::getEngineTimings();
	}

	/* ── スタックトレース収集 ── */

	public static function captureTrace(string $label, int $depth = 15): void {
		\AIS\System\DiagnosticsManager::captureTrace($label, $depth);
	}

	public static function getCapturedTraces(): array {
		return \AIS\System\DiagnosticsManager::getCapturedTraces();
	}

	/* ── エラー・ログ収集 ── */

	public static function registerErrorHandler(): void {
		\AIS\System\DiagnosticsManager::registerErrorHandler();
	}

	public static function logDebugEvent(string $category, string $message, array $context = []): void {
		\AIS\System\DiagnosticsManager::logDebugEvent($category, $message, $context);
	}

	public static function logSlowExecution(string $label, float $elapsed, float $threshold = 1000.0): void {
		\AIS\System\DiagnosticsManager::logSlowExecution($label, $elapsed, $threshold);
	}

	public static function checkMemoryUsage(): void {
		\AIS\System\DiagnosticsManager::checkMemoryUsage();
	}

	public static function logRaceCondition(string $resource, string $detail = ''): void {
		\AIS\System\DiagnosticsManager::logRaceCondition($resource, $detail);
	}

	public static function logIntegrationError(string $service, int $httpCode = 0, string $detail = ''): void {
		\AIS\System\DiagnosticsManager::logIntegrationError($service, $httpCode, $detail);
	}

	public static function logEnvironmentIssue(string $issue, array $context = []): void {
		\AIS\System\DiagnosticsManager::logEnvironmentIssue($issue, $context);
	}

	public static function logTimingIssue(string $operation, float $elapsed, float $limit): void {
		\AIS\System\DiagnosticsManager::logTimingIssue($operation, $elapsed, $limit);
	}

	public static function logError(array $entry): void {
		\AIS\System\DiagnosticsManager::logError($entry);
	}

	public static function log(string $category, string $message, array $context = []): void {
		\AIS\System\DiagnosticsManager::log($category, $message, $context);
	}

	/* ── ログ管理 ── */

	public static function rotateIfNeeded(): void {
		\AIS\System\DiagnosticsManager::rotateIfNeeded();
	}

	public static function purgeExpiredLogs(): void {
		\AIS\System\DiagnosticsManager::purgeExpiredLogs();
	}

	/* ── トレンド ── */

	public static function getTrends(int $days = 7): array {
		return \AIS\System\DiagnosticsManager::getTrends($days);
	}

	/* ── データ収集 ── */

	public static function collect(): array {
		return \AIS\System\DiagnosticsManager::collect();
	}

	public static function collectBasic(): array {
		return \AIS\System\DiagnosticsManager::collectBasic();
	}

	public static function collectExtended(): array {
		return \AIS\System\DiagnosticsManager::collectExtended();
	}

	public static function collectDebug(): array {
		return \AIS\System\DiagnosticsManager::collectDebug();
	}

	public static function collectWithUnsent(string $lastSent): array {
		return \AIS\System\DiagnosticsManager::collectWithUnsent($lastSent);
	}

	/* ── 送信 ── */

	public static function maybeSend(): void {
		\AIS\System\DiagnosticsManager::maybeSend();
	}

	public static function send(array $data): bool {
		return \AIS\System\DiagnosticsManager::send($data);
	}

	/* ── UI / 統計 ── */

	public static function preview(): array {
		return \AIS\System\DiagnosticsManager::preview();
	}

	public static function getLogSummary(): array {
		return \AIS\System\DiagnosticsManager::getLogSummary();
	}

	public static function getSecuritySummary(): array {
		return \AIS\System\DiagnosticsManager::getSecuritySummary();
	}

	public static function healthCheck(bool $detailed = false): array {
		return \AIS\System\DiagnosticsManager::healthCheck($detailed);
	}

	public static function getAllLogs(): array {
		return \AIS\System\DiagnosticsManager::getAllLogs();
	}

	public static function clearLogs(): void {
		\AIS\System\DiagnosticsManager::clearLogs();
	}

	/* ── 通知 ── */

	public static function shouldShowNotice(): bool {
		return \AIS\System\DiagnosticsManager::shouldShowNotice();
	}

	public static function markNoticeShown(): void {
		\AIS\System\DiagnosticsManager::markNoticeShown();
	}
}
