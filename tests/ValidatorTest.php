<?php
/**
 * ValidatorTest - 入力バリデーションのテスト
 */
class ValidatorTest extends TestCase {

	/* ═══ 基本バリデーション ═══ */

	public function testRequiredPass(): void {
		$v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
		$this->assertTrue($v->validate());
	}

	public function testRequiredFail(): void {
		$v = Validator::make(['name' => ''], ['name' => 'required']);
		$this->assertTrue($v->fails());
		$this->assertTrue($v->hasError('name'));
	}

	public function testRequiredMissing(): void {
		$v = Validator::make([], ['name' => 'required']);
		$this->assertTrue($v->fails());
	}

	/* ═══ メール ═══ */

	public function testEmailValid(): void {
		$v = Validator::make(['email' => 'test@example.com'], ['email' => 'email']);
		$this->assertTrue($v->validate());
	}

	public function testEmailInvalid(): void {
		$v = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
		$this->assertTrue($v->fails());
	}

	public function testEmailEmptySkipped(): void {
		$v = Validator::make(['email' => ''], ['email' => 'email']);
		$this->assertTrue($v->validate());
	}

	/* ═══ 文字数制限 ═══ */

	public function testMinStringPass(): void {
		$v = Validator::make(['name' => 'Alice'], ['name' => 'min:3']);
		$this->assertTrue($v->validate());
	}

	public function testMinStringFail(): void {
		$v = Validator::make(['name' => 'AB'], ['name' => 'min:3']);
		$this->assertTrue($v->fails());
	}

	public function testMaxStringPass(): void {
		$v = Validator::make(['name' => 'Alice'], ['name' => 'max:10']);
		$this->assertTrue($v->validate());
	}

	public function testMaxStringFail(): void {
		$v = Validator::make(['name' => 'Very Long Name Here'], ['name' => 'max:5']);
		$this->assertTrue($v->fails());
	}

	/* ═══ 型バリデーション ═══ */

	public function testNumericValid(): void {
		$v = Validator::make(['price' => '19.99'], ['price' => 'numeric']);
		$this->assertTrue($v->validate());
	}

	public function testNumericInvalid(): void {
		$v = Validator::make(['price' => 'abc'], ['price' => 'numeric']);
		$this->assertTrue($v->fails());
	}

	public function testIntegerValid(): void {
		$v = Validator::make(['age' => '25'], ['age' => 'integer']);
		$this->assertTrue($v->validate());
	}

	public function testIntegerInvalid(): void {
		$v = Validator::make(['age' => '25.5'], ['age' => 'integer']);
		$this->assertTrue($v->fails());
	}

	public function testAlphaValid(): void {
		$v = Validator::make(['code' => 'ABC'], ['code' => 'alpha']);
		$this->assertTrue($v->validate());
	}

	public function testAlphaInvalid(): void {
		$v = Validator::make(['code' => 'AB1'], ['code' => 'alpha']);
		$this->assertTrue($v->fails());
	}

	public function testAlphaNumValid(): void {
		$v = Validator::make(['code' => 'ABC123'], ['code' => 'alphaNum']);
		$this->assertTrue($v->validate());
	}

	/* ═══ URL ═══ */

	public function testUrlValid(): void {
		$v = Validator::make(['site' => 'https://example.com'], ['site' => 'url']);
		$this->assertTrue($v->validate());
	}

	public function testUrlInvalid(): void {
		$v = Validator::make(['site' => 'not-a-url'], ['site' => 'url']);
		$this->assertTrue($v->fails());
	}

	/* ═══ in ═══ */

	public function testInValid(): void {
		$v = Validator::make(['status' => 'active'], ['status' => 'in:active,inactive,pending']);
		$this->assertTrue($v->validate());
	}

	public function testInInvalid(): void {
		$v = Validator::make(['status' => 'deleted'], ['status' => 'in:active,inactive,pending']);
		$this->assertTrue($v->fails());
	}

	/* ═══ 日付・boolean ═══ */

	public function testDateValid(): void {
		$v = Validator::make(['date' => '2024-01-01'], ['date' => 'date']);
		$this->assertTrue($v->validate());
	}

	public function testDateInvalid(): void {
		$v = Validator::make(['date' => 'not-a-date'], ['date' => 'date']);
		$this->assertTrue($v->fails());
	}

	public function testBooleanValid(): void {
		$v = Validator::make(['flag' => true], ['flag' => 'boolean']);
		$this->assertTrue($v->validate());
	}

	public function testBooleanInvalid(): void {
		$v = Validator::make(['flag' => 'yes'], ['flag' => 'boolean']);
		$this->assertTrue($v->fails());
	}

	/* ═══ AP独自: slug ═══ */

	public function testSlugValid(): void {
		$v = Validator::make(['slug' => 'my-page_01'], ['slug' => 'slug']);
		$this->assertTrue($v->validate());
	}

	public function testSlugInvalid(): void {
		$v = Validator::make(['slug' => 'my page!'], ['slug' => 'slug']);
		$this->assertTrue($v->fails());
	}

	/* ═══ 複合ルール ═══ */

	public function testMultipleRules(): void {
		$v = Validator::make(
			['name' => 'AB', 'email' => 'bad'],
			['name' => 'required|min:3', 'email' => 'required|email']
		);
		$this->assertTrue($v->fails());
		$this->assertTrue($v->hasError('name'));
		$this->assertTrue($v->hasError('email'));
	}

	public function testMultipleRulesPass(): void {
		$v = Validator::make(
			['name' => 'Alice', 'email' => 'alice@example.com'],
			['name' => 'required|min:2|max:100', 'email' => 'required|email']
		);
		$this->assertTrue($v->validate());
	}

	/* ═══ カスタムメッセージ ═══ */

	public function testCustomMessage(): void {
		$v = Validator::make(
			['name' => ''],
			['name' => 'required'],
			['name.required' => '名前を入力してください']
		);
		$v->validate();
		$this->assertEquals('名前を入力してください', $v->first('name'));
	}

	/* ═══ エラー取得 ═══ */

	public function testFirstReturnsNullWhenNoError(): void {
		$v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
		$v->validate();
		$this->assertNull($v->first('name'));
	}

	public function testErrorsReturnsArray(): void {
		$v = Validator::make([], ['name' => 'required', 'email' => 'required']);
		$v->validate();
		$errors = $v->errors();
		$this->assertArrayHasKey('name', $errors);
		$this->assertArrayHasKey('email', $errors);
	}
}
