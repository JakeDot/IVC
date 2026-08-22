/**
 * IRC object model — TypeScript port.
 *
 * Re-exports all public classes and types from the IRC/ layer.
 * Import subclasses after IvcMarshaller so registrations run in correct order.
 */

export { ModeEntry }      from './ModeEntry.js';
export { IrcObject }      from './IvcObject.js';
export { IvcMarshaller }  from './IvcMarshaller.js';
export { IvcClient }      from './IvcClient.js';
// Subclasses register themselves with IvcMarshaller on import
export { Network }        from './Network.js';
export { Channel }        from './Channel.js';
export { UserNick }       from './UserNick.js';
export type {
  IvcApiResponse,
  IvcStatus,
  IvcResponse,
  IvcAccessResult,
  IvcParsedUri,
  ModeMap,
} from './types.js';
