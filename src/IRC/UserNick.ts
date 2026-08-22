/**
 * UserNick — IVC user nickname object (@nick).
 *
 * Mirrors PHP `Fortress\IRC\Serv\NameServ` object semantics.
 *
 * Usage:
 *   const user = await UserNick.fromServer('@CyberFox');
 *   console.log(user.isIdentified);
 *   console.log(user.domain);
 */

import { IrcObject } from './IvcObject.js';
import { IvcClient }    from './IvcClient.js';
import { IvcMarshaller } from './IvcMarshaller.js';
import type { IvcApiResponse } from './types.js';

export class UserNick extends IrcObject {
  constructor(private readonly _nick: string) {
    super();
  }

  get target(): string {
    return this._nick.startsWith('@') ? this._nick : '@' + this._nick;
  }

  get nick(): string {
    return this._nick.replace(/^@/, '');
  }

  // ---------------------------------------------------------------
  // Cached mode flag helpers
  // ---------------------------------------------------------------

  /** True if the user is currently identified (mode +i or §identified=1). */
  get isIdentified(): boolean {
    return this.hasMode('i') || this.getProp('§identified') === '1';
  }

  /** True if the user is registered (mode +r). */
  get isRegistered(): boolean {
    return this.hasMode('r');
  }

  /** User's custom domain property (§domain). */
  get domain(): string | null {
    return this.getProp('§domain');
  }

  /** Standardised username (nick@domain or nick). */
  get standardizedUsername(): string {
    const d = this.domain;
    return d ? `${this.nick}@${d}` : this.nick;
  }

  // ---------------------------------------------------------------
  // Factory / auto-marshalling
  // ---------------------------------------------------------------

  static fromBody(body: IvcApiResponse): UserNick {
    const raw  = (body.base_target as string | undefined) ?? '';
    const nick = raw.replace(/^@/, '');
    const user = new UserNick(nick);
    return IrcObject._applyBody(user, body);
  }

  /**
   * Fetch a user object from the server and return a hydrated UserNick.
   *
   *   const user = await UserNick.fromServer('CyberFox');
   *   console.log(user.domain);
   */
  static async fromServer(nick: string): Promise<UserNick> {
    const target = nick.startsWith('@') ? nick : '@' + nick;
    const result = await IvcClient.fetchObject(target);
    const user   = UserNick.fromBody({ ...result.body, base_target: target });
    if (result.status) user._lastStatus = result.status;
    return user;
  }
}

// Register '@' prefix with the marshaller.
IvcMarshaller.register('@', UserNick);
