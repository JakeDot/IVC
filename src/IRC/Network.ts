/**
 * Network — global unnamed IRC object (£).
 *
 * Mirrors PHP `Fortress\IRC\Objects\Network`.
 *
 * Server-wide configuration is stored as a single mode string under
 * the key '£', e.g.:  +§maxchans=500+§motd=Welcome+§flood_limit=10
 *
 * Usage:
 *   const net = await Network.fromServer();
 *   const motd = net.get('§motd');              // → "Welcome"
 *   await net.set('§motd', 'Hello', operNick);  // persists to server
 *   const all  = net.all();                     // → { "§motd": "Welcome", … }
 */

import { IrcObject } from './IvcObject.js';
import { IvcClient }    from './IvcClient.js';
import { IvcMarshaller } from './IvcMarshaller.js';
import { ModeEntry }    from './ModeEntry.js';
import type { IvcApiResponse, IvcResponse } from './types.js';

export class Network extends IrcObject {
  static readonly OBJECT_KEY = '£';

  get target(): string {
    return Network.OBJECT_KEY;
  }

  // ---------------------------------------------------------------
  // Convenience property API — mirrors PHP Network::get/set/unset/all
  // ---------------------------------------------------------------

  /**
   * Get a server property from the locally cached mode map.
   *
   *   net.get('§maxchans');         // '500' or null
   *   net.get('§maxchans', '256');  // '256' if unset
   */
  get(key: string, def: string | null = null): string | null {
    return this.getProp(key, def);
  }

  /**
   * Fetch a server property directly from the server (no local cache).
   * Equivalent to refresh() + get().
   */
  static async fetch(key: string, def: string | null = null): Promise<string | null> {
    const net = await Network.fromServer();
    return net.get(key, def);
  }

  /**
   * Set a server property (requires IRCop — enforced server-side).
   *
   *   await Network.set('§maxchans', '500', operNick);
   *   // or on an instance:
   *   await net.set('§motd', 'Hello World', operNick);
   */
  async set(key: string, value: string, requester: string = ''): Promise<IvcResponse> {
    return this.setModes(`+${key}=${value}`, requester);
  }

  static async set(key: string, value: string, requester: string = ''): Promise<IvcResponse> {
    return IvcClient.applyModes(Network.OBJECT_KEY, `+${key}=${value}`, requester);
  }

  /**
   * Unset a server property (requires IRCop).
   *
   *   await Network.unset('§maxchans', operNick);
   */
  async unset(key: string, requester: string = ''): Promise<IvcResponse> {
    return this.setModes(`-${key}`, requester);
  }

  static async unset(key: string, requester: string = ''): Promise<IvcResponse> {
    return IvcClient.applyModes(Network.OBJECT_KEY, `-${key}`, requester);
  }

  /**
   * Return all active §key=value server properties as a plain object.
   *
   *   net.all();  // { "§maxchans": "500", "§motd": "Welcome" }
   */
  all(): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [k, v] of this._modes) {
      if (v instanceof ModeEntry && v.isSet && v.val !== null) {
        out[k] = v.val;
      }
    }
    return out;
  }

  // ---------------------------------------------------------------
  // Factory / auto-marshalling
  // ---------------------------------------------------------------

  /**
   * Hydrate a Network instance from an IVC API response body.
   * Called automatically by IvcMarshaller.fromResponse().
   */
  static fromBody(body: IvcApiResponse): Network {
    const net = new Network();
    return IrcObject._applyBody(net, body);
  }

  /**
   * Fetch the £ object from the server and return a hydrated Network.
   *
   *   const net = await Network.fromServer();
   *   console.log(net.get('§motd'));
   */
  static async fromServer(): Promise<Network> {
    const result = await IvcClient.fetchObject(Network.OBJECT_KEY);
    const net    = Network.fromBody(result.body);
    if (result.status) net._lastStatus = result.status;
    return net;
  }
}

// Register with the marshaller so fromResponse() auto-hydrates '£' targets.
IvcMarshaller.register('£', Network);
