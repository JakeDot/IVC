/**
 * Channel — IVC channel object (#channel / &channel).
 *
 * Mirrors PHP `Fortress\IRC\Serv\ChanServ` object semantics.
 *
 * Usage:
 *   const chan = await Channel.fromServer('#fortress');
 *   const isOp = chan.hasMode('o');
 *   const access = await chan.checkAccess('CyberFox');
 */

import { IrcObject } from './IvcObject.js';
import { IvcClient }    from './IvcClient.js';
import { IvcMarshaller } from './IvcMarshaller.js';
import type { IvcAccessResult, IvcApiResponse, IvcResponse } from './types.js';

export class Channel extends IrcObject {
  constructor(private readonly _name: string) {
    super();
  }

  get target(): string {
    return this._name;
  }

  get name(): string {
    return this._name;
  }

  // ---------------------------------------------------------------
  // Channel-specific helpers
  // ---------------------------------------------------------------

  /**
   * Normalise a channel name — ensures a leading '#'.
   */
  static normalize(name: string): string {
    name = name.trim();
    if (!name.startsWith('#') && !name.startsWith('&')) {
      name = '#' + name;
    }
    return name;
  }

  /**
   * Check access for a user against this channel's mode flags.
   * Delegates to the server's /api/access.php endpoint.
   */
  async checkAccess(user?: string): Promise<IvcAccessResult> {
    const result = await IvcClient.checkAccess(this._name, user);
    return result.body as IvcAccessResult;
  }

  /**
   * True if the locally cached modes include +o (operator flag).
   * For user-specific op status, use checkAccess().
   */
  get isSecret(): boolean   { return this.hasMode('s'); }
  get isModerated(): boolean { return this.hasMode('m'); }
  get isTopicLocked(): boolean { return this.hasMode('t'); }
  get isInviteOnly(): boolean { return this.hasMode('i'); }
  get passkey(): string | null { return this.getProp('k'); }

  // ---------------------------------------------------------------
  // Factory / auto-marshalling
  // ---------------------------------------------------------------

  static fromBody(body: IvcApiResponse): Channel {
    const name = (body.base_target as string | undefined) ?? '';
    const chan  = new Channel(name);
    return IrcObject._applyBody(chan, body);
  }

  /**
   * Fetch a channel object from the server and return a hydrated Channel.
   *
   *   const chan = await Channel.fromServer('#fortress');
   *   console.log(chan.isModerated);
   */
  static async fromServer(name: string): Promise<Channel> {
    name = Channel.normalize(name);
    const result = await IvcClient.fetchObject(name);
    const chan   = Channel.fromBody({ ...result.body, base_target: name });
    if (result.status) chan._lastStatus = result.status;
    return chan;
  }
}

// Register '# ' and '&' prefixes with the marshaller.
IvcMarshaller.register('#', Channel);
IvcMarshaller.register('&', Channel);
