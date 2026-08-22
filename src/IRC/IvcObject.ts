/**
 * IvcObject — abstract base class for all IVC IRC objects.
 *
 * Mirrors PHP `Fortress\IRC\IrcObject` / `NoModeTrait`.
 *
 * Provides:
 *   - Local mode string parsing / serialisation (via IvcMarshaller)
 *   - `setModes()` — sends a mode delta to the server and merges the result
 *   - `getModes()` — returns the cached mode map (populate via refresh())
 *   - `refresh()` — re-fetches the object from the server and updates local state
 */

import { IvcMarshaller } from './IvcMarshaller.js';
import { IvcClient } from './IvcClient.js';
import type { IvcApiResponse, IvcResponse, IvcStatus, ModeMap } from './types.js';

export abstract class IrcObject {
  /** Raw packed mode string as last received from server. */
  protected _rawModes: string = '';

  /** Parsed mode map. Populated by parseModes() / refresh(). */
  protected _modes: ModeMap = new Map();

  /** IVC Status header from the last server response. */
  protected _lastStatus: IvcStatus | null = null;

  // ---------------------------------------------------------------
  // Abstract contract (mirrors PHP)
  // ---------------------------------------------------------------

  /** Canonical IVC target string (e.g. "#fortress", "£", "@CyberFox"). */
  abstract get target(): string;

  // ---------------------------------------------------------------
  // Mode string codec (delegates to IvcMarshaller)
  // ---------------------------------------------------------------

  /** Parse a raw mode string and cache the result. */
  parseModes(raw: string): ModeMap {
    this._rawModes = raw;
    this._modes    = IvcMarshaller.parseModeString(raw);
    return this._modes;
  }

  /** Serialise the cached mode map back to a packed mode string. */
  toModeString(): string {
    return IvcMarshaller.toModeString(this._modes);
  }

  /** Return the currently cached mode map. */
  getModes(): ModeMap {
    return this._modes;
  }

  /** Check whether a boolean flag mode is currently set. */
  hasMode(key: string): boolean {
    const entry = this._modes.get(key);
    if (entry === undefined) return false;
    if (typeof entry === 'boolean') return entry;
    return entry.plus;
  }

  /** Get the value of a §key=value property, or null/default if not set. */
  getProp(key: string, def: string | null = null): string | null {
    const entry = this._modes.get(key);
    if (!entry || typeof entry === 'boolean') return def;
    return entry.isSet ? (entry.val ?? def) : def;
  }

  // ---------------------------------------------------------------
  // Server interaction
  // ---------------------------------------------------------------

  /**
   * Send a mode delta to the server and update local state on success.
   *
   *   await channel.setModes('+§topic=Hello World');
   *   await network.setModes('-§motd');
   */
  async setModes(delta: string, requester: string = ''): Promise<IvcResponse> {
    const res = await IvcClient.applyModes(this.target, delta, requester);
    if (res.success && res.modes !== undefined) {
      this.parseModes(res.modes);
    }
    if (res.status) this._lastStatus = res.status;
    return res;
  }

  /**
   * Re-fetch this object's mode state from the server.
   * Returns `this` for chaining: `await network.refresh()`.
   */
  async refresh(): Promise<this> {
    const result = await IvcClient.fetchObject(this.target);
    if (result.body.modes !== undefined) {
      this.parseModes(result.body.modes as string);
    }
    if (result.status) this._lastStatus = result.status;
    return this;
  }

  /** The IVC Status header received in the last server round-trip. */
  get lastStatus(): IvcStatus | null {
    return this._lastStatus;
  }

  // ---------------------------------------------------------------
  // Static factory helper (called by subclasses)
  // ---------------------------------------------------------------

  /** Populate an existing instance from a raw API response body. */
  protected static _applyBody<T extends IrcObject>(instance: T, body: IvcApiResponse): T {
    if (body.modes) instance.parseModes(body.modes as string);
    return instance;
  }
}
