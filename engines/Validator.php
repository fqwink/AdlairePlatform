<?php
/**
 * Validator - 入力バリデーション
 *
 * AFE.Utilities.php の Validator を AdlairePlatform エンジンパターンに適応。
 *
 * 使用例:
 *   $v = Validator::make($data, [
 *       'name'  => 'required|min:2|max:100',
 *       'email' => 'required|email',
 *   ]);
 *   if ($v->fails()) { $errors = $v->errors(); }
 *
 * 対応ルール:
 *   required, email, min:N, max:N, numeric, integer,
 *   alpha, alphaNum, url, in:a,b,c, date, boolean, slug, regex:pattern
 */
class Validator {

	private array $data;
	private array $rules;
	private array $errors = [];
	private array $messages = [];

	private function __construct(array $data, array $rules, array $messages = []) {
		$this->data = $data;
		$this->rules = $rules;
		$this->messages = $messages;
	}

	public static function make(array $data, array $rules, array $messages = []): self {
		return new self($data, $rules, $messages);
	}

	public function validate(): bool {
		$this->errors = [];
		foreach ($this->rules as $field => $rules) {
			$rulesArray = is_string($rules) ? explode('|', $rules) : $rules;
			foreach ($rulesArray as $rule) {
				$this->applyRule($field, $rule);
			}
		}
		return empty($this->errors);
	}

	public function fails(): bool {
		return !$this->validate();
	}

	public function errors(): array {
		return $this->errors;
	}

	public function first(string $field): ?string {
		return $this->errors[$field][0] ?? null;
	}

	public function hasError(string $field): bool {
		return isset($this->errors[$field]);
	}

	/* ── ルール適用 ── */

	private function applyRule(string $field, string $rule): void {
		$value = $this->data[$field] ?? null;
		if (str_contains($rule, ':')) {
			[$ruleName, $parameter] = explode(':', $rule, 2);
		} else {
			$ruleName = $rule;
			$parameter = null;
		}
		$method = 'validate' . ucfirst($ruleName);
		if (method_exists($this, $method)) {
			$this->$method($field, $value, $parameter);
		}
	}

	/* ── バリデーションルール ── */

	private function validateRequired(string $field, $value, ?string $p = null): void {
		if (is_null($value) || $value === '') {
			$this->addError($field, 'required', "{$field} は必須です");
		}
	}

	private function validateEmail(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
			$this->addError($field, 'email', "{$field} は有効なメールアドレスではありません");
		}
	}

	private function validateMin(string $field, $value, ?string $parameter): void {
		if (is_null($value) || $value === '') return;
		$min = (int)$parameter;
		if (is_string($value) && mb_strlen($value, 'UTF-8') < $min) {
			$this->addError($field, 'min', "{$field} は{$min}文字以上必要です");
		} elseif (is_numeric($value) && $value < $min) {
			$this->addError($field, 'min', "{$field} は{$min}以上必要です");
		}
	}

	private function validateMax(string $field, $value, ?string $parameter): void {
		if (is_null($value) || $value === '') return;
		$max = (int)$parameter;
		if (is_string($value) && mb_strlen($value, 'UTF-8') > $max) {
			$this->addError($field, 'max', "{$field} は{$max}文字以下にしてください");
		} elseif (is_numeric($value) && $value > $max) {
			$this->addError($field, 'max', "{$field} は{$max}以下にしてください");
		}
	}

	private function validateNumeric(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && !is_numeric($value)) {
			$this->addError($field, 'numeric', "{$field} は数値でなければなりません");
		}
	}

	private function validateInteger(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
			$this->addError($field, 'integer', "{$field} は整数でなければなりません");
		}
	}

	private function validateAlpha(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && !ctype_alpha($value)) {
			$this->addError($field, 'alpha', "{$field} はアルファベットのみ使用できます");
		}
	}

	private function validateAlphaNum(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && !ctype_alnum($value)) {
			$this->addError($field, 'alphaNum', "{$field} は英数字のみ使用できます");
		}
	}

	private function validateUrl(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
			$this->addError($field, 'url', "{$field} は有効なURLではありません");
		}
	}

	private function validateIn(string $field, $value, ?string $parameter): void {
		if (is_null($value) || $value === '') return;
		$allowed = explode(',', $parameter ?? '');
		if (!in_array($value, $allowed, true)) {
			$this->addError($field, 'in', "{$field} は次のいずれかでなければなりません: " . implode(', ', $allowed));
		}
	}

	private function validateDate(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && strtotime($value) === false) {
			$this->addError($field, 'date', "{$field} は有効な日付ではありません");
		}
	}

	private function validateBoolean(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
			$this->addError($field, 'boolean', "{$field} は真偽値でなければなりません");
		}
	}

	private function validateSlug(string $field, $value, ?string $p = null): void {
		if (!is_null($value) && $value !== '' && !preg_match('/^[a-zA-Z0-9_\-]+$/', $value)) {
			$this->addError($field, 'slug', "{$field} はスラグ形式で入力してください");
		}
	}

	private function validateRegex(string $field, $value, ?string $parameter): void {
		if (is_null($value) || $value === '') return;
		if ($parameter && !preg_match($parameter, $value)) {
			$this->addError($field, 'regex', "{$field} の形式が正しくありません");
		}
	}

	/* ── エラー管理 ── */

	private function addError(string $field, string $rule, string $message): void {
		$key = "{$field}.{$rule}";
		$this->errors[$field][] = $this->messages[$key] ?? $message;
	}
}
