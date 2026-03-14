<?php
/**
 * MailerEngine - メール送信の抽象化
 *
 * E-3 fix: ApiEngine から mail() 直接呼び出しを分離。
 * リトライ、ログ出力、テスト時のモック対応を提供。
 *
 * 使用例:
 *   MailerEngine::send($to, $subject, $body, $replyTo);
 *   MailerEngine::sendContact($name, $email, $message);
 */
class MailerEngine {

	/** @var \AIS\Deployment\Mailer|null Ver.1.5 Framework メーラー */
	private static ?\AIS\Deployment\Mailer $fwMailer = null;

	/**
	 * Ver.1.5: Framework Mailer インスタンスを取得する
	 */
	public static function getMailer(): \AIS\Deployment\Mailer {
		if (self::$fwMailer === null) {
			self::$fwMailer = new \AIS\Deployment\Mailer();
		}
		return self::$fwMailer;
	}

	/** リトライ回数（初回含まず） */
	private const MAX_RETRIES = 2;

	/** リトライ間隔（秒） */
	private const RETRY_DELAY = 1;

	/** テストモード（true の場合メール送信をスキップ） */
	private static bool $testMode = false;

	/** テストモード時に送信されたメールを保存 */
	private static array $sentMails = [];

	/**
	 * テストモードを有効化（ユニットテスト用）
	 */
	public static function enableTestMode(): void {
		self::$testMode = true;
		self::$sentMails = [];
	}

	/**
	 * テストモードを無効化
	 */
	public static function disableTestMode(): void {
		self::$testMode = false;
		self::$sentMails = [];
	}

	/**
	 * テストモードで送信されたメール一覧を取得
	 */
	public static function getSentMails(): array {
		return self::$sentMails;
	}

	/**
	 * メールを送信（リトライ付き）
	 *
	 * @param string $to       送信先
	 * @param string $subject  件名
	 * @param string $body     本文
	 * @param string $replyTo  返信先（空の場合はヘッダーに含めない）
	 * @param array  $extraHeaders 追加ヘッダー
	 * @return bool 送信成功なら true
	 */
	public static function send(
		string $to,
		string $subject,
		string $body,
		string $replyTo = '',
		array $extraHeaders = []
	): bool {
		/* ヘッダインジェクション対策 */
		$to      = self::sanitizeHeader($to);
		$subject = self::sanitizeHeader($subject);
		$replyTo = self::sanitizeHeader($replyTo);

		/* ヘッダー構築 */
		$headers = 'Content-Type: text/plain; charset=UTF-8';
		if ($replyTo !== '') {
			$headers .= "\r\nFrom: {$replyTo}\r\nReply-To: {$replyTo}";
		}
		foreach ($extraHeaders as $name => $value) {
			$headers .= "\r\n" . self::sanitizeHeader($name) . ': ' . self::sanitizeHeader($value);
		}

		/* テストモード */
		if (self::$testMode) {
			self::$sentMails[] = [
				'to'       => $to,
				'subject'  => $subject,
				'body'     => $body,
				'replyTo'  => $replyTo,
				'headers'  => $headers,
				'time'     => date('c'),
			];
			Logger::debug('MailerEngine: テストモード — メール送信スキップ', ['to' => $to, 'subject' => $subject]);
			return true;
		}

		/* リトライ付き送信 */
		$lastAttempt = self::MAX_RETRIES;
		for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
			if (@mail($to, $subject, $body, $headers)) {
				Logger::info('メール送信成功', [
					'to'      => $to,
					'subject' => $subject,
					'attempt' => $attempt + 1,
				]);
				return true;
			}

			if ($attempt < $lastAttempt) {
				Logger::warning('メール送信失敗、リトライ中', [
					'to'      => $to,
					'attempt' => $attempt + 1,
					'max'     => self::MAX_RETRIES + 1,
				]);
				sleep(self::RETRY_DELAY);
			}
		}

		Logger::error('メール送信失敗（リトライ上限）', [
			'to'      => $to,
			'subject' => $subject,
		]);
		return false;
	}

	/**
	 * お問い合わせフォーム用の送信ヘルパー
	 *
	 * @param string $to      送信先メールアドレス
	 * @param string $name    送信者名
	 * @param string $email   送信者メール
	 * @param string $message メッセージ本文
	 * @param string $siteTitle サイト名（件名に使用）
	 * @return bool 送信成功なら true
	 */
	public static function sendContact(
		string $to,
		string $name,
		string $email,
		string $message,
		string $siteTitle = 'AP'
	): bool {
		$safeName  = self::sanitizeHeader($name);
		$safeTitle = self::sanitizeHeader($siteTitle);

		$subject = '【' . $safeTitle . '】お問い合わせ: ' . $safeName;
		$body    = "名前: {$safeName}\nメール: {$email}\n\n{$message}";

		return self::send($to, $subject, $body, $email);
	}

	/**
	 * ヘッダインジェクション対策: CR/LF とヌルバイトを除去
	 */
	private static function sanitizeHeader(string $value): string {
		return str_replace(["\r", "\n", "\0"], '', $value);
	}
}
