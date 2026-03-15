<?php
/**
 * MailerEngine - 後方互換シム
 *
 * Ver.1.8: 全ロジックを AIS\Deployment\MailerService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \AIS\Deployment\MailerService を使用してください
 */
class MailerEngine {

	/** @deprecated */
	public static function enableTestMode(): void {
		\AIS\Deployment\MailerService::enableTestMode();
	}

	/** @deprecated */
	public static function disableTestMode(): void {
		\AIS\Deployment\MailerService::disableTestMode();
	}

	/** @deprecated */
	public static function getSentMails(): array {
		return \AIS\Deployment\MailerService::getSentMails();
	}

	/** @deprecated */
	public static function send(
		string $to,
		string $subject,
		string $body,
		string $replyTo = '',
		array $extraHeaders = []
	): bool {
		return \AIS\Deployment\MailerService::send($to, $subject, $body, $replyTo, $extraHeaders);
	}

	/** @deprecated */
	public static function sendContact(
		string $to,
		string $name,
		string $email,
		string $message,
		string $siteTitle = 'AP'
	): bool {
		return \AIS\Deployment\MailerService::sendContact($to, $name, $email, $message, $siteTitle);
	}
}
