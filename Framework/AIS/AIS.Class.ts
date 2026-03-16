/**
 * Adlaire Infrastructure Services (AIS) — Data Models & Enums
 *
 * AIS 固有の列挙型・データモデルを定義する。
 *
 * @package AIS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// Errors
// ============================================================================

/**
 * インフラストラクチャエラー
 */
export class InfrastructureError extends Error {
  constructor(
    message: string,
    public readonly code: string = "INFRA_ERROR",
  ) {
    super(message);
    this.name = "InfrastructureError";
  }
}

/**
 * 設定検証エラー
 */
export class ConfigValidationError extends InfrastructureError {
  constructor(
    message: string,
    public readonly errors: string[],
  ) {
    super(message, "CONFIG_VALIDATION_ERROR");
    this.name = "ConfigValidationError";
  }
}
