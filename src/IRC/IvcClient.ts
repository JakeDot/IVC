/**
 * IvcClient — HTTP transport layer for the IVC API.
 *
 * Wraps the Fetch API (browser or Node 18+) to:
 *   - Send requests to the IVC server with standard headers
 *   - Parse the `Status:` response header on every response
 *   - Invoke IvcMarshaller.fromResponse() to auto-hydrate typed objects
 *
 * Configuration:
 *   IvcClient.configure({ baseUrl: 'https://my.ivc.host' });
 *
 * Defaults (in order):
 *   1. Last value passed to configure()
 *   2. process.env.IVC_BASE_URL   (Node)
 *   3. window.location.origin     (browser)
 *   4. ''                         (relative URLs — same-origin)
 */

import { IvcMarshaller } from './IvcMarshaller.js';
import type { IvcApiResponse, IvcResponse, IvcStatus } from './types.js';

export interface IvcClientConfig {
  baseUrl?: string;
  /** Additional headers merged into every request. */
  headers?: Record<string, string>;
  /** Request timeout in milliseconds (default 10 000). */
  timeoutMs?: number;
}

interface IvcRawResult {
  body:   IvcApiResponse;
  status: IvcStatus | null;
  object: unknown | null;
}

let _config: IvcClientConfig = {};

export class IvcClient {
  // ---------------------------------------------------------------
  // Configuration
  // ---------------------------------------------------------------

  static configure(cfg: IvcClientConfig): void {
    _config = { ..._config, ...cfg };
  }

  static get baseUrl(): string {
    if (_config.baseUrl) return _config.baseUrl.replace(/\/$/, '');
    if (typeof process !== 'undefined' && process.env?.['IVC_BASE_URL']) {
      return (process.env['IVC_BASE_URL'] as string).replace(/\/$/, '');
    }
    if (typeof window !== 'undefined') return window.location.origin;
    return '';
  }

  // ---------------------------------------------------------------
  // Core request
  // ---------------------------------------------------------------

  private static async request(
    method:  'GET' | 'POST' | 'PUT' | 'DELETE',
    path:    string,
    body?:   Record<string, unknown>,
  ): Promise<IvcRawResult> {
    const url  = `${IvcClient.baseUrl}${path}`;
    const ctrl = new AbortController();
    const tid  = setTimeout(() => ctrl.abort(), _config.timeoutMs ?? 10_000);

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'X-IVC-Client':  'ts/1.0',
      ...(_config.headers ?? {}),
    };

    const init: RequestInit = {
      method,
      headers,
      signal: ctrl.signal,
    };
    if (body !== undefined) {
      init.body = JSON.stringify(body);
    }

    let res: Response;
    try {
      res = await fetch(url, init);
    } finally {
      clearTimeout(tid);
    }

    const responseBody = await res.json() as IvcApiResponse;

    // Parse Status: header
    let ivcStatus: IvcStatus | null = null;
    const statusHeader = res.headers.get('Status') ?? res.headers.get('X-IVC-Status');
    if (statusHeader) {
      ivcStatus = IvcMarshaller.parseStatusHeader(statusHeader);
    }

    // Auto-unmarshal
    const object = IvcMarshaller.fromResponse(responseBody);

    return { body: responseBody, status: ivcStatus, object };
  }

  // ---------------------------------------------------------------
  // HTTP verbs
  // ---------------------------------------------------------------

  static async get(path: string): Promise<IvcRawResult> {
    return IvcClient.request('GET', path);
  }

  static async post(path: string, body: Record<string, unknown>): Promise<IvcRawResult> {
    return IvcClient.request('POST', path, body);
  }

  static async put(path: string, body: Record<string, unknown>): Promise<IvcRawResult> {
    return IvcClient.request('PUT', path, body);
  }

  static async delete(path: string, body?: Record<string, unknown>): Promise<IvcRawResult> {
    return IvcClient.request('DELETE', path, body);
  }

  // ---------------------------------------------------------------
  // IVC-specific helpers
  // ---------------------------------------------------------------

  /**
   * Fetch the current mode string for a target object from the server.
   * Endpoint: GET /api/object.php?target=<target>
   */
  static async fetchObject(target: string): Promise<IvcRawResult> {
    const encoded = encodeURIComponent(target);
    return IvcClient.get(`/api/object.php?target=${encoded}`);
  }

  /**
   * Apply a mode delta to a target object.
   * Endpoint: POST /api/modes.php
   * Body: { target, delta, requester }
   */
  static async applyModes(
    target:    string,
    delta:     string,
    requester: string = '',
  ): Promise<IvcResponse> {
    const result = await IvcClient.post('/api/modes.php', { target, delta, requester });
    return {
      success:     result.body.success,
      message:     result.body.message ?? '',
      base_target: result.body.base_target,
      modes:       result.body.modes,
      object:      result.object as IvcResponse['object'],
      status:      result.status ?? undefined,
    };
  }

  /**
   * Check access to a target (mirrors ChanServ::checkAccess).
   * Endpoint: GET /api/access.php?target=<uri>&user=<nick>
   */
  static async checkAccess(target: string, user?: string): Promise<IvcRawResult> {
    let path = `/api/access.php?target=${encodeURIComponent(target)}`;
    if (user) path += `&user=${encodeURIComponent(user)}`;
    return IvcClient.get(path);
  }
}
