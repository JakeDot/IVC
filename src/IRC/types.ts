/**
 * IVC Shared type definitions.
 */

import type { ModeEntry } from './ModeEntry.js';
import type { IrcObject } from './IvcObject.js';

/** Parsed response body from IVC API endpoints. */
export interface IvcApiResponse {
  success: boolean;
  message?: string;
  base_target?: string;
  modes?: string;
  mode_flags?: Record<string, unknown>;
  data?: Record<string, unknown>;
  [key: string]: unknown;
}

/** Parsed `Status:` response header. */
export interface IvcStatus {
  /** HTTP numeric status code extracted from the Status header. */
  httpCode: number;
  /** Authenticated nick in the current session. */
  nick: string;
  /** Active subscribed targets with their inline modes. */
  targets: Array<{ name: string; modes: string }>;
  /** Raw header string for debugging. */
  raw: string;
}

/** Result of a mode-set operation. */
export interface IvcResponse {
  success: boolean;
  message: string;
  base_target?: string;
  modes?: string;
  object?: IrcObject;
  status?: IvcStatus;
}

/** Access-check result (mirrors ChanServ::checkAccess). */
export interface IvcAccessResult {
  success: boolean;
  code?: number;
  message?: string;
  base_target: string;
  target?: string;
  modes?: string;
  mode_flags?: Record<string, unknown>;
}

/** Parsed `ivc://` URI. */
export interface IvcParsedUri {
  scheme: string;           // "ivc"
  host: string;             // e.g. "local.host"
  prefix: string;           // "#", "&", "@", "£", "$", ""
  target: string;           // base object name (without prefix)
  modes: string;            // inline mode string e.g. "+ov"
  props: Record<string, { value: string; modes: string }>;
  events: Record<string, { value: string; modes: string }>;
  raw: string;              // original URI
}

/** Mode map type alias. */
export type ModeMap = Map<string, ModeEntry | boolean>;
