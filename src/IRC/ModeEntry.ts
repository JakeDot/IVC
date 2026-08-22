/**
 * IVC ModeEntry — value object representing a single parsed IRC mode.
 *
 * Flag-only modes:  plus / minus are set; val is null.
 *   e.g.  +o  → ModeEntry(plus=true,  minus=false, val=null)
 *         -t  → ModeEntry(plus=false, minus=true,  val=null)
 *
 * Key=value modes (§prop):  val holds the value string; plus indicates it is set.
 *   e.g.  +§motd=Welcome → ModeEntry(plus=true, minus=false, val="Welcome")
 *
 * Mirrors PHP `Fortress\IRC\ModeEntry`.
 */
export class ModeEntry {
  constructor(
    public readonly plus:  boolean      = false,
    public readonly minus: boolean      = false,
    public readonly val:   string | null = null,
  ) {}

  /** True if this entry is actively set (plus and not minus). */
  get isSet(): boolean {
    return this.plus && !this.minus;
  }

  /** True if this is a key=value property entry (§ key). */
  get isKeyValue(): boolean {
    return this.val !== null;
  }
}
