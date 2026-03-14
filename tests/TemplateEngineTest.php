<?php
/**
 * TemplateEngineTest - テンプレートエンジンの変数展開・フィルタ・制御構文テスト
 */
class TemplateEngineTest extends TestCase {

	/* ═══ 変数展開 ═══ */

	public function testSimpleVariable(): void {
		$html = TemplateEngine::render('Hello {{name}}!', ['name' => 'World']);
		$this->assertContains('Hello World!', $html);
	}

	public function testVariableEscaped(): void {
		$html = TemplateEngine::render('{{content}}', ['content' => '<script>alert("xss")</script>']);
		$this->assertNotContains('<script>', $html);
		$this->assertContains('&lt;script&gt;', $html);
	}

	public function testRawVariableNotEscaped(): void {
		$html = TemplateEngine::render('{{{content}}}', ['content' => '<b>bold</b>']);
		$this->assertContains('<b>bold</b>', $html);
	}

	public function testMissingVariableEmpty(): void {
		$html = TemplateEngine::render('Hello {{missing}}!', []);
		$this->assertContains('Hello !', $html);
	}

	/* ═══ ネストプロパティ ═══ */

	public function testNestedProperty(): void {
		$ctx = ['user' => ['name' => 'Alice', 'email' => 'alice@example.com']];
		$html = TemplateEngine::render('{{user.name}} - {{user.email}}', $ctx);
		$this->assertContains('Alice', $html);
		$this->assertContains('alice@example.com', $html);
	}

	public function testDeepNestedProperty(): void {
		$ctx = ['a' => ['b' => ['c' => 'deep']]];
		$html = TemplateEngine::render('{{a.b.c}}', $ctx);
		$this->assertContains('deep', $html);
	}

	public function testMissingNestedPropertyEmpty(): void {
		$html = TemplateEngine::render('{{user.name}}', ['user' => []]);
		$this->assertEquals('', trim($html));
	}

	/* ═══ フィルター ═══ */

	public function testFilterUpper(): void {
		$html = TemplateEngine::render('{{name|upper}}', ['name' => 'hello']);
		$this->assertContains('HELLO', $html);
	}

	public function testFilterLower(): void {
		$html = TemplateEngine::render('{{name|lower}}', ['name' => 'HELLO']);
		$this->assertContains('hello', $html);
	}

	public function testFilterCapitalize(): void {
		$html = TemplateEngine::render('{{name|capitalize}}', ['name' => 'hello world']);
		$this->assertContains('Hello World', $html);
	}

	public function testFilterTrim(): void {
		$html = TemplateEngine::render('[{{name|trim}}]', ['name' => '  spaced  ']);
		$this->assertContains('[spaced]', $html);
	}

	public function testFilterTruncate(): void {
		$html = TemplateEngine::render('{{text|truncate:5}}', ['text' => 'Hello World']);
		$this->assertContains('Hello...', $html);
	}

	public function testFilterTruncateShortText(): void {
		$html = TemplateEngine::render('{{text|truncate:100}}', ['text' => 'Short']);
		$this->assertContains('Short', $html);
		$this->assertNotContains('...', $html);
	}

	public function testFilterDefault(): void {
		$html = TemplateEngine::render('{{name|default:Guest}}', ['name' => '']);
		$this->assertContains('Guest', $html);
	}

	public function testFilterDefaultNotAppliedWhenValueExists(): void {
		$html = TemplateEngine::render('{{name|default:Guest}}', ['name' => 'Alice']);
		$this->assertContains('Alice', $html);
		$this->assertNotContains('Guest', $html);
	}

	public function testFilterLength(): void {
		$html = TemplateEngine::render('{{name|length}}', ['name' => 'Hello']);
		$this->assertContains('5', $html);
	}

	public function testFilterChain(): void {
		$html = TemplateEngine::render('{{name|trim|upper}}', ['name' => '  hello  ']);
		$this->assertContains('HELLO', $html);
	}

	/* ═══ 条件分岐 {{#if}} ═══ */

	public function testIfTrue(): void {
		$html = TemplateEngine::render('{{#if show}}visible{{/if}}', ['show' => true]);
		$this->assertContains('visible', $html);
	}

	public function testIfFalse(): void {
		$html = TemplateEngine::render('{{#if show}}visible{{/if}}', ['show' => false]);
		$this->assertNotContains('visible', $html);
	}

	public function testIfElse(): void {
		$html = TemplateEngine::render('{{#if show}}yes{{else}}no{{/if}}', ['show' => false]);
		$this->assertContains('no', $html);
		$this->assertNotContains('yes', $html);
	}

	public function testIfNegation(): void {
		$html = TemplateEngine::render('{{#if !hidden}}visible{{/if}}', ['hidden' => false]);
		$this->assertContains('visible', $html);
	}

	public function testIfNestedProperty(): void {
		$ctx = ['user' => ['active' => true]];
		$html = TemplateEngine::render('{{#if user.active}}active{{/if}}', $ctx);
		$this->assertContains('active', $html);
	}

	public function testIfNestedBlocks(): void {
		$tpl = '{{#if a}}A{{#if b}}B{{/if}}{{/if}}';
		$html = TemplateEngine::render($tpl, ['a' => true, 'b' => true]);
		$this->assertContains('AB', $html);

		$html2 = TemplateEngine::render($tpl, ['a' => true, 'b' => false]);
		$this->assertContains('A', $html2);
		$this->assertNotContains('B', $html2);
	}

	/* ═══ ループ {{#each}} ═══ */

	public function testEachArray(): void {
		$ctx = ['items' => [['name' => 'A'], ['name' => 'B'], ['name' => 'C']]];
		$html = TemplateEngine::render('{{#each items}}{{name}},{{/each}}', $ctx);
		$this->assertContains('A,B,C,', $html);
	}

	public function testEachEmpty(): void {
		$html = TemplateEngine::render('{{#each items}}{{name}}{{/each}}', ['items' => []]);
		$this->assertEquals('', trim($html));
	}

	public function testEachIndex(): void {
		$ctx = ['items' => [['v' => 'x'], ['v' => 'y']]];
		$html = TemplateEngine::render('{{#each items}}{{@index}}:{{v}},{{/each}}', $ctx);
		$this->assertContains('0:x,1:y,', $html);
	}

	public function testEachFirstLast(): void {
		$ctx = ['items' => [['v' => 'A'], ['v' => 'B'], ['v' => 'C']]];
		$tpl = '{{#each items}}{{#if @first}}[{{/if}}{{v}}{{#if @last}}]{{/if}}{{/each}}';
		$html = TemplateEngine::render($tpl, $ctx);
		$this->assertContains('[A', $html);
		$this->assertContains('C]', $html);
	}

	public function testEachScalarThis(): void {
		$ctx = ['tags' => ['php', 'css', 'js']];
		$html = TemplateEngine::render('{{#each tags}}{{this}},{{/each}}', $ctx);
		$this->assertContains('php,css,js,', $html);
	}

	public function testEachProtectsSecurityKeys(): void {
		/* admin キーはループ内アイテムで上書きされないべき */
		$ctx = [
			'admin' => true,
			'csrf_token' => 'secret123',
			'items' => [['admin' => false, 'csrf_token' => 'hacked', 'name' => 'test']],
		];
		$tpl = '{{#each items}}{{admin}}{{csrf_token}}{{/each}}';
		$html = TemplateEngine::render($tpl, $ctx);
		/* 保護キーは親コンテキストの値が維持される */
		$this->assertContains('1', $html);       /* admin=true → "1" */
		$this->assertContains('secret123', $html); /* csrf_token は上書きされない */
		$this->assertNotContains('hacked', $html);
	}

	/* ═══ ネストされた each ═══ */

	public function testNestedEach(): void {
		$ctx = [
			'groups' => [
				['title' => 'G1', 'items' => [['name' => 'A'], ['name' => 'B']]],
				['title' => 'G2', 'items' => [['name' => 'C']]],
			],
		];
		$tpl = '{{#each groups}}[{{title}}:{{#each items}}{{name}}{{/each}}]{{/each}}';
		$html = TemplateEngine::render($tpl, $ctx);
		$this->assertContains('[G1:AB]', $html);
		$this->assertContains('[G2:C]', $html);
	}

	/* ═══ エッジケース ═══ */

	public function testEmptyTemplate(): void {
		$html = TemplateEngine::render('', ['name' => 'test']);
		$this->assertEquals('', $html);
	}

	public function testNoVariablesInTemplate(): void {
		$html = TemplateEngine::render('<p>Hello</p>', []);
		$this->assertContains('<p>Hello</p>', $html);
	}

	public function testMultipleSameVariable(): void {
		$html = TemplateEngine::render('{{x}} and {{x}}', ['x' => 'val']);
		$this->assertContains('val and val', $html);
	}
}
