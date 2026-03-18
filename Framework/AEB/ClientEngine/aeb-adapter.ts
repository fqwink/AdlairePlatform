/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />

/**
 * AEB Adapter - ES6 モジュール → グローバルスコープブリッジ
 *
 * Framework/AEB の ES6 モジュールをグローバルスコープに公開し、
 * wysiwyg.js や editInplace.js から利用可能にする。
 *
 * window.AEB = { Core, Blocks, Utils } としてアクセス可能。
 * wysiwyg.js は editor:save / editor:autosave イベントを AEB EventBus に発行する。
 */
(async function (): Promise<void> {
	'use strict';

	interface AEBCoreModule {
		EventBus: new () => {
			on(event: string, callback: (...args: unknown[]) => void): void;
			off(event: string, callback: (...args: unknown[]) => void): void;
			emit(event: string, ...args: unknown[]): void;
		};
		BlockRegistry: { register(name: string, cls: unknown): void };
		StateManager: unknown;
		HistoryManager: unknown;
		Editor: new (config: Record<string, unknown>) => unknown;
	}

	interface AEBBlocksModule {
		BaseBlock: unknown;
		ParagraphBlock: unknown;
		HeadingBlock: unknown;
		ListBlock: unknown;
		QuoteBlock: unknown;
		CodeBlock: unknown;
		ImageBlock: unknown;
		TableBlock: unknown;
		ChecklistBlock: unknown;
		DelimiterBlock: unknown;
	}

	interface AEBUtilsModule {
		sanitizer: unknown;
		dom: unknown;
		selection: unknown;
		keyboard: unknown;
	}

	const basePath: string = document.querySelector('base')?.href || '/';

	/* ── ES6 モジュールの動的 import ── */
	const [Core, Blocks, Utils] = await Promise.all([
		import(basePath + 'Framework/AEB/AEB.Core.js') as Promise<AEBCoreModule>,
		import(basePath + 'Framework/AEB/AEB.Blocks.js') as Promise<AEBBlocksModule>,
		import(basePath + 'Framework/AEB/AEB.Utils.js') as Promise<AEBUtilsModule>,
	]);

	/* ── グローバルに公開 ── */
	window.AEB = {
		/* Core コンポーネント */
		EventBus: Core.EventBus,
		BlockRegistry: Core.BlockRegistry,
		StateManager: Core.StateManager,
		HistoryManager: Core.HistoryManager,
		Editor: Core.Editor,

		/* ブロックタイプ */
		Blocks: {
			BaseBlock: Blocks.BaseBlock,
			ParagraphBlock: Blocks.ParagraphBlock,
			HeadingBlock: Blocks.HeadingBlock,
			ListBlock: Blocks.ListBlock,
			QuoteBlock: Blocks.QuoteBlock,
			CodeBlock: Blocks.CodeBlock,
			ImageBlock: Blocks.ImageBlock,
			TableBlock: Blocks.TableBlock,
			ChecklistBlock: Blocks.ChecklistBlock,
			DelimiterBlock: Blocks.DelimiterBlock,
		},

		/* ユーティリティ */
		Utils: {
			sanitizer: Utils.sanitizer,
			dom: Utils.dom,
			selection: Utils.selection,
			keyboard: Utils.keyboard,
		},
	} as Window['AEB'];

	/* ── グローバル EventBus インスタンス（ap-events.js 互換） ── */
	if (!window.__AP_EventBus__) {
		window.__AP_EventBus__ = new Core.EventBus();
	}

	/* ── 読み込み完了通知 ── */
	window.dispatchEvent(new CustomEvent('aeb:ready', { detail: window.AEB }));
	console.debug('[AEB Adapter] Framework modules loaded');
})();
