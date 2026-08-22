/**
 * IvcMarshaller — mode string codec and IVC URI / response auto-unmarshaller.
 *
 * Provides the shared parsing algorithm that mirrors PHP
 * `IrcObject::parseModeStringToArray` / `arrayToModeString` / `parseSubobjects`,
 * plus HTTP response → typed IrcObject hydration.
 *
 * All methods are static — the marshaller has no instance state.
 */

import { ModeEntry } from './ModeEntry.js';
import type { IvcApiResponse, IvcParsedUri, IvcStatus, ModeMap } from './types.js';

// Forward references resolved at runtime to avoid circular imports.
// The concrete subclass constructors are registered via `IvcMarshaller.register()`.
type ObjectConstructor = { fromBody(body: IvcApiResponse): unknown };

const registry = new Map<string, ObjectConstructor>();

export class IvcMarshaller {
  // ---------------------------------------------------------------
  // Subclass registry (avoids circular imports)
  // ---------------------------------------------------------------

  /**
   * Register a prefix character → subclass mapping so `fromResponse` can
   * hydrate the correct IrcObject subtype.
   *
   *   IvcMarshaller.register('£', Network);
   *   IvcMarshaller.register('#', Channel);
   *   IvcMarshaller.register('&', Channel);
   *   IvcMarshaller.register('@', UserNick);
   */
  static register(prefix: string, ctor: ObjectConstructor): void {
    registry.set(prefix, ctor);
  }

  // ---------------------------------------------------------------
  // Mode string codec — mirrors PHP parseModeStringToArray / arrayToModeString
  // ---------------------------------------------------------------

  /**
   * Parse an IVC mode string into a `Map<key, ModeEntry | boolean>`.
   *
   * Input:  "+§maxchans=500+§motd=Welcome-t+o"
   * Output: Map {
   *   "§maxchans" → ModeEntry(plus=true,  val="500"),
   *   "§motd"     → ModeEntry(plus=true,  val="Welcome"),
   *   "t"         → ModeEntry(minus=true, val=null),
   *   "o"         → ModeEntry(plus=true,  val=null),
   * }
   *
   * Section-symbol (§) keys are always key=value pairs.
   * Single-character keys are boolean flag modes.
   * The spread operator handles multibyte Unicode correctly.
   */
  static parseModeString(raw: string): ModeMap {
    const result: ModeMap = new Map();
    const chars = [...raw.trim()]; // unicode-safe split
    let i = 0;
    let sign: '+' | '-' | '0' = '+';

    while (i < chars.length) {
      const ch = chars[i];

      if (ch === '+') { sign = '+'; i++; continue; }
      if (ch === '-') { sign = '-'; i++; continue; }
      if (ch === '0') { sign = '0'; i++; continue; }

      if (ch === '§') {
        // Consume §key[=value] until next sign delimiter
        let token = '§';
        i++;
        while (i < chars.length && chars[i] !== '+' && chars[i] !== '-' && chars[i] !== '0') {
          token += chars[i++];
        }
        const eqPos = token.indexOf('=');
        if (eqPos !== -1) {
          const key = token.substring(0, eqPos);
          const val = token.substring(eqPos + 1);
          result.set(key, new ModeEntry(sign === '+', sign === '-', val));
        } else {
          result.set(token, new ModeEntry(sign === '+', sign === '-', null));
        }
        continue;
      }

      // Single-character boolean flag mode
      result.set(ch, new ModeEntry(sign === '+', sign === '-', null));
      i++;
    }

    return result;
  }

  /**
   * Pack a ModeMap back into an IVC mode string.
   *
   * Input:  Map { "§motd" → ModeEntry(plus=true, val="Hello"), "o" → ModeEntry(plus=true) }
   * Output: "+§motd=Hello+o"
   */
  static toModeString(modes: ModeMap): string {
    let out = '';
    for (const [key, entry] of modes) {
      if (typeof entry === 'boolean') {
        out += (entry ? '+' : '-') + key;
      } else if (entry instanceof ModeEntry) {
        const sign = entry.plus ? '+' : (entry.minus ? '-' : '0');
        out += sign + key;
        if (entry.val !== null) out += '=' + entry.val;
      }
    }
    return out;
  }

  // ---------------------------------------------------------------
  // ivc:// URI parser — mirrors PHP IrcServices::parseSubobjects
  // ---------------------------------------------------------------

  /**
   * Parse an `ivc://` URI into its constituent parts.
   *
   * Examples:
   *   ivc://local.host/#fortress+ov   → { host:"local.host", prefix:"#", target:"fortress", modes:"+ov" }
   *   ivc://£§motd=Hello              → { host:"", prefix:"£", target:"", props:{ §motd:{value:"Hello"} } }
   *   ivc://:comment-1Δreactions      → { host:"", prefix:"", target:":comment-1", events:{ reactions:{} } }
   */
  static parseUri(uri: string): IvcParsedUri {
    const raw = uri;
    let rest = uri;

    // Strip scheme
    const scheme = rest.startsWith('ivc://') ? 'ivc' : '';
    if (scheme) rest = rest.slice(6);

    // Extract host (up to first /)
    let host = '';
    const slashPos = rest.indexOf('/');
    if (slashPos !== -1) {
      host = rest.slice(0, slashPos);
      rest = rest.slice(slashPos + 1);
    }

    // Object prefixes
    const PREFIXES = ['£', '$', '@', '&', '#'];
    let prefix = '';
    for (const p of PREFIXES) {
      if (rest.startsWith(p)) {
        prefix = p;
        rest = rest.slice(p.length);
        break;
      }
    }

    // Split on §prop and Δevent markers
    const SEC  = '§';
    const D1   = 'Δ';
    const D2   = '∆';

    let baseEnd = rest.length;
    for (let ci = 0; ci < [...rest].length; ci++) {
      const ch = [...rest][ci];
      if (ch === SEC || ch === D1 || ch === D2) {
        // byte offset
        baseEnd = [...rest].slice(0, ci).join('').length;
        break;
      }
    }
    let base = rest.slice(0, baseEnd);
    rest = rest.slice(baseEnd);

    // Inline modes on base target (e.g. #room+ov)
    let modes = '';
    const plusPos = base.indexOf('+');
    if (plusPos !== -1) {
      modes = base.slice(plusPos);
      base  = base.slice(0, plusPos);
    }

    // Parse §props and Δevents
    const props:  IvcParsedUri['props']  = {};
    const events: IvcParsedUri['events'] = {};

    const subChars = [...rest];
    let si = 0;
    while (si < subChars.length) {
      const sym = subChars[si];
      if (sym === SEC) {
        let token = '';
        si++;
        while (si < subChars.length && subChars[si] !== SEC && subChars[si] !== D1 && subChars[si] !== D2) {
          token += subChars[si++];
        }
        const eq = token.indexOf('=');
        const name  = eq !== -1 ? '§' + token.slice(0, eq) : '§' + token;
        const value = eq !== -1 ? token.slice(eq + 1) : '';
        props[name] = { value, modes: '' };
      } else if (sym === D1 || sym === D2) {
        let token = '';
        si++;
        while (si < subChars.length && subChars[si] !== SEC && subChars[si] !== D1 && subChars[si] !== D2) {
          token += subChars[si++];
        }
        const eq = token.indexOf('=');
        const name  = eq !== -1 ? token.slice(0, eq) : token;
        const value = eq !== -1 ? token.slice(eq + 1) : '';
        events[name] = { value, modes: '' };
      } else {
        si++;
      }
    }

    return { scheme, host, prefix, target: base, modes, props, events, raw };
  }

  // ---------------------------------------------------------------
  // Status: header parser
  // ---------------------------------------------------------------

  /**
   * Parse the IVC `Status:` response header.
   *
   * Format: "200+modes:CyberFox{subs [#room+t+o, #lobby]}"
   * Returns: { httpCode: 200, nick: "CyberFox", targets: [{ name:"#room", modes:"+t+o" }] }
   */
  static parseStatusHeader(header: string): IvcStatus {
    const raw = header.trim();
    const result: IvcStatus = { httpCode: 0, nick: '', targets: [], raw };

    const codePlusIdx = raw.indexOf('+modes:');
    if (codePlusIdx !== -1) {
      result.httpCode = parseInt(raw.slice(0, codePlusIdx), 10) || 0;
      const afterModes = raw.slice(codePlusIdx + 7); // "+modes:".length = 7

      const braceOpen  = afterModes.indexOf('{');
      const braceClose = afterModes.lastIndexOf('}');

      if (braceOpen !== -1) {
        result.nick = afterModes.slice(0, braceOpen).trim();

        if (braceClose > braceOpen) {
          const inner = afterModes.slice(braceOpen + 1, braceClose); // "subs [#room+t+o]"
          const subsMatch = inner.match(/subs\s*\[([^\]]*)\]/);
          if (subsMatch) {
            const parts = subsMatch[1].split(',').map(s => s.trim()).filter(Boolean);
            for (const part of parts) {
              const plusIdx = part.indexOf('+');
              if (plusIdx !== -1) {
                result.targets.push({ name: part.slice(0, plusIdx), modes: part.slice(plusIdx) });
              } else {
                result.targets.push({ name: part, modes: '' });
              }
            }
          }
        }
      } else {
        result.nick = afterModes.trim();
      }
    } else {
      result.httpCode = parseInt(raw, 10) || 0;
    }

    return result;
  }

  // ---------------------------------------------------------------
  // Response → IrcObject auto-unmarshal
  // ---------------------------------------------------------------

  /**
   * Hydrate the correct IrcObject subclass from an IVC API response body.
   * Returns null if no matching registered prefix is found.
   *
   * Registration happens in each subclass file via:
   *   IvcMarshaller.register('£', Network);
   */
  static fromResponse(body: IvcApiResponse): unknown | null {
    const bt = (body.base_target ?? '').trim();
    if (!bt) return null;

    const prefix = [...bt][0] ?? '';
    const ctor = registry.get(prefix);
    if (!ctor) return null;

    return ctor.fromBody(body);
  }
}
