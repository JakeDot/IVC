package ivc;

/**
 * ModeEntry — value object representing a single parsed IRC mode.
 *
 * Flag-only modes:  plus/minus set; val is null.
 * Key=value modes:  val holds the value; plus indicates set.
 *
 * Mirrors PHP Fortress\IRC\ModeEntry and TypeScript ModeEntry.
 */
public record ModeEntry(boolean plus, boolean minus, String val) {

    public boolean isSet()      { return plus && !minus; }
    public boolean isKeyValue() { return val != null; }

    public static ModeEntry plusFlag()                      { return new ModeEntry(true,  false, null); }
    public static ModeEntry minusFlag()                     { return new ModeEntry(false, true,  null); }
    public static ModeEntry unset()                         { return new ModeEntry(false, false, null); }
    public static ModeEntry keyValue(boolean plus, String v){ return new ModeEntry(plus,  !plus, v);   }
}