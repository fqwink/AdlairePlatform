<?php
/**
 * DiagnosticController - 診断・テレメトリ設定
 *
 * DiagnosticEngine の handle() private ハンドラを Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 * @since Ver.1.7-37 sendNow/clearLogs 完全実装（Engine exit 委譲を除去）
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class DiagnosticController extends BaseController {

	/** 診断収集の有効/無効切替 */
	public function setEnabled(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$enabled = !empty($request->post('enabled'));
		\DiagnosticEngine::setEnabled($enabled);
		\AdminEngine::logActivity('診断収集: ' . ($enabled ? '有効' : '無効'));
		return $this->ok(['enabled' => $enabled]);
	}

	/** 収集レベル変更 */
	public function setLevel(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$level = trim($request->post('level', 'basic'));
		if (!in_array($level, ['basic', 'extended', 'debug'], true)) {
			return $this->error('無効なレベル: basic, extended, debug のいずれかを指定してください');
		}
		\DiagnosticEngine::setLevel($level);
		\AdminEngine::logActivity('診断レベル変更: ' . $level);
		return $this->ok(['level' => $level]);
	}

	/** 現在のバッファプレビュー */
	public function preview(Request $request): Response {
		$data = \DiagnosticEngine::healthCheck(true);
		return Response::json(['ok' => true, 'data' => $data]);
	}

	/** 即時送信 */
	public function sendNow(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$config = \DiagnosticEngine::loadConfig();
		$data = \DiagnosticEngine::collectWithUnsent($config['last_sent'] ?? '');
		$success = \DiagnosticEngine::send($data);

		if ($success) {
			$config = \DiagnosticEngine::loadConfig();
			$config['last_sent'] = date('c');
			$config['consecutive_failures'] = 0;
			$config['circuit_breaker_until'] = 0;
			\DiagnosticEngine::saveConfig($config);
			\DiagnosticEngine::purgeExpiredLogs();
			\AdminEngine::logActivity('診断データを手動送信');
			return $this->ok(['message' => '送信しました（ログは14日間保持）', 'sent_at' => date('c')]);
		}

		return $this->error('送信に失敗しました（エンドポイントに到達できない可能性があります）');
	}

	/** ログクリア */
	public function clearLogs(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		\DiagnosticEngine::clearLogs();
		\AdminEngine::logActivity('診断ログをクリア');
		return $this->ok(['message' => 'ログをクリアしました']);
	}

	/** ログ取得 */
	public function getLogs(Request $request): Response {
		$timings = \DiagnosticEngine::getTimings();
		$engineTimings = \DiagnosticEngine::getEngineTimings();
		return Response::json(['ok' => true, 'data' => [
			'timings' => $timings,
			'engine_timings' => $engineTimings,
		]]);
	}

	/** サマリー取得 */
	public function getSummary(Request $request): Response {
		$data = \DiagnosticEngine::healthCheck(false);
		return Response::json(['ok' => true, 'data' => $data]);
	}

	/** ヘルスチェック */
	public function health(Request $request): Response {
		$detailed = !empty($request->post('detailed'));
		$data = \DiagnosticEngine::healthCheck($detailed);
		return Response::json(['ok' => true, 'data' => $data]);
	}
}
