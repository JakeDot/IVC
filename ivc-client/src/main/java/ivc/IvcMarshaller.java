package ivc;

import java.util.LinkedHashMap;
import java.util.Map;
import java.util.ArrayList;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * IvcMarshaller — mode string codec and IVC URI / response auto-unmarshaller.
 *
 * Provides the shared parsing algorithm that mirrors PHP
 * IrcObject::parseModeStringToArray / arrayToModeString / IrcServices::parseSubobjects,
 * plus HTTP response -> typed IrcObject hydration.
 *
 * All methods are static.
 */
public final class IvcMarshaller {

    private IvcMarshaller() {}

    // ---------------------------------------------------------------
    // Mode string codec
    // ---------------------------------------------------------------

    /**
     * Parse an IVC mode string into a Map<key, Object> where values are
     * either a {@link ModeEntry} (for §key=value pairs and single-char flags)
     * or {@code Boolean} (legacy boolean shorthand).
     *
     * Input:  "+§maxchans=500+§motd=Welcome-t+o"
     * Output: { "§maxchans" -> ModeEntry(true,false,"500"),
     *           "§motd"     -> ModeEntry(true,false,"Welcome"),
     *           "t"         -> ModeEntry(false,true,null),
     *           "o"         -> ModeEntry(true,false,null) }
     */
    public static Map<String, Object> parseModeString(String raw) {
        Map<String, Object> result = new LinkedHashMap<>();
        if (raw == null || raw.isBlank()) return result;

        // Use codepoint iteration for proper Unicode handling (§ is U+00A7).
        int[] cps = raw.codePoints().toArray();
        int i     = 0;
        char sign = '+';

        final int CP_PLUS    = '+';
        final int CP_MINUS   = '-';
        final int CP_ZERO    = '0';
        final int CP_SECTION = '\u00A7'; // §

        while (i < cps.length) {
            int cp = cps[i];

            if (cp == CP_PLUS)    { sign = '+'; i++; continue; }
            if (cp == CP_MINUS)   { sign = '-'; i++; continue; }
            if (cp == CP_ZERO)    { sign = '0'; i++; continue; }

            if (cp == CP_SECTION) {
                // Consume §key[=value] until next sign delimiter
                StringBuilder token = new StringBuilder().appendCodePoint(cp);
                i++;
                while (i < cps.length && cps[i] != CP_PLUS && cps[i] != CP_MINUS && cps[i] != CP_ZERO) {
                    token.appendCodePoint(cps[i++]);
                }
                String t    = token.toString();
                int eqPos   = t.indexOf('=');
                if (eqPos != -1) {
                    String key = t.substring(0, eqPos);
                    String val = t.substring(eqPos + 1);
                    result.put(key, new ModeEntry(sign == '+', sign == '-', val));
                } else {
                    result.put(t, new ModeEntry(sign == '+', sign == '-', null));
                }
                continue;
            }

            // Single-character boolean flag mode
            String key = new String(Character.toChars(cp));
            result.put(key, new ModeEntry(sign == '+', sign == '-', null));
            i++;
        }

        return result;
    }

    /**
     * Pack a mode map back into an IVC mode string.
     *
     * Input:  { "§motd" -> ModeEntry(true,false,"Hello"), "o" -> ModeEntry(true,false,null) }
     * Output: "+§motd=Hello+o"
     */
    public static String toModeString(Map<String, Object> modes) {
        StringBuilder sb = new StringBuilder();
        for (Map.Entry<String, Object> e : modes.entrySet()) {
            String key = e.getKey();
            Object val = e.getValue();
            if (val instanceof Boolean b) {
                sb.append(b ? '+' : '-').append(key);
            } else if (val instanceof ModeEntry me) {
                char sign = me.plus() ? '+' : (me.minus() ? '-' : '0');
                sb.append(sign).append(key);
                if (me.val() != null) sb.append('=').append(me.val());
            }
        }
        return sb.toString();
    }

    // ---------------------------------------------------------------
    // ivc:// URI parser
    // ---------------------------------------------------------------

    private static final String PREFIXES = "#&@£$";

    /**
     * Parse an {@code ivc://} URI into its constituent parts.
     */
    public static IvcParsedUri parseUri(String uri) {
        String raw    = uri;
        String rest   = uri;
        String scheme = "";

        if (rest.startsWith("ivc://")) {
            scheme = "ivc";
            rest   = rest.substring(6);
        }

        // Extract host
        String host = "";
        int slashPos = rest.indexOf('/');
        if (slashPos != -1) {
            host = rest.substring(0, slashPos);
            rest = rest.substring(slashPos + 1);
        }

        // Object prefix
        String prefix = "";
        for (char p : PREFIXES.toCharArray()) {
            if (rest.startsWith(String.valueOf(p))) {
                prefix = String.valueOf(p);
                rest = rest.substring(1);
                break;
            }
        }
        // § is multibyte: check explicitly
        if (prefix.isEmpty() && rest.startsWith("\u00A3")) { // £
            prefix = "\u00A3";
            rest = rest.substring("\u00A3".length());
        }

        // Find first subobject marker
        int secPos   = rest.indexOf('\u00A7'); // §
        int deltaPos = -1;
        int d1 = rest.indexOf('\u0394'); // Δ
        int d2 = rest.indexOf('\u221A'); // ∆ (alt)
        if (d1 != -1 && d2 != -1) deltaPos = Math.min(d1, d2);
        else if (d1 != -1) deltaPos = d1;
        else if (d2 != -1) deltaPos = d2;

        int firstSub = -1;
        if (secPos != -1 && deltaPos != -1) firstSub = Math.min(secPos, deltaPos);
        else if (secPos   != -1) firstSub = secPos;
        else if (deltaPos != -1) firstSub = deltaPos;

        String base = firstSub != -1 ? rest.substring(0, firstSub) : rest;
        String subRest = firstSub != -1 ? rest.substring(firstSub) : "";

        // Inline modes on base
        String modes = "";
        int plusPos = base.indexOf('+');
        if (plusPos != -1) {
            modes = base.substring(plusPos);
            base  = base.substring(0, plusPos);
        }

        // Props & events
        Map<String, String> props  = new LinkedHashMap<>();
        Map<String, String> events = new LinkedHashMap<>();
        parseSubobjects(subRest, props, events);

        return new IvcParsedUri(scheme, host, prefix, base, modes, props, events, raw);
    }

    private static void parseSubobjects(String s, Map<String,String> props, Map<String,String> events) {
        int[] cps = s.codePoints().toArray();
        int i = 0;
        while (i < cps.length) {
            int cp = cps[i];
            if (cp == '\u00A7') { // §
                StringBuilder token = new StringBuilder();
                i++;
                while (i < cps.length && cps[i] != '\u00A7' && cps[i] != '\u0394' && cps[i] != '\u221A') {
                    token.appendCodePoint(cps[i++]);
                }
                String t = token.toString();
                int eq = t.indexOf('=');
                String name  = eq != -1 ? "\u00A7" + t.substring(0, eq) : "\u00A7" + t;
                String value = eq != -1 ? t.substring(eq + 1) : "";
                props.put(name, value);
            } else if (cp == '\u0394' || cp == '\u221A') { // Δ / ∆
                StringBuilder token = new StringBuilder();
                i++;
                while (i < cps.length && cps[i] != '\u00A7' && cps[i] != '\u0394' && cps[i] != '\u221A') {
                    token.appendCodePoint(cps[i++]);
                }
                String t = token.toString();
                int eq = t.indexOf('=');
                String name  = eq != -1 ? t.substring(0, eq) : t;
                String value = eq != -1 ? t.substring(eq + 1) : "";
                events.put(name, value);
            } else {
                i++;
            }
        }
    }

    // ---------------------------------------------------------------
    // Status: header parser
    // ---------------------------------------------------------------

    private static final Pattern STATUS_PATTERN =
            Pattern.compile("^(\\d+)\\+modes:([^\\{]+)\\{subs\\s*\\[([^\\]]*)\\]\\}$");

    /**
     * Parse the IVC {@code Status:} response header.
     *
     * Format: "200+modes:CyberFox{subs [#room+t+o]}"
     */
    public static IvcStatus parseStatusHeader(String header) {
        if (header == null || header.isBlank()) return IvcStatus.unknown();
        String raw = header.trim();
        Matcher m  = STATUS_PATTERN.matcher(raw);
        if (!m.matches()) {
            int code = 0;
            try { code = Integer.parseInt(raw.split("\\+")[0]); } catch (NumberFormatException ignored) {}
            return new IvcStatus(code, "", List.of(), raw);
        }
        int    code    = Integer.parseInt(m.group(1));
        String nick    = m.group(2).trim();
        String subsStr = m.group(3).trim();
        List<IvcStatus.Target> targets = new ArrayList<>();
        if (!subsStr.isEmpty()) {
            for (String part : subsStr.split(",")) {
                part = part.trim();
                int plusIdx = part.indexOf('+');
                if (plusIdx != -1) {
                    targets.add(new IvcStatus.Target(part.substring(0, plusIdx), part.substring(plusIdx)));
                } else if (!part.isEmpty()) {
                    targets.add(new IvcStatus.Target(part, ""));
                }
            }
        }
        return new IvcStatus(code, nick, List.copyOf(targets), raw);
    }

    // ---------------------------------------------------------------
    // Response -> IrcObject auto-unmarshal
    // ---------------------------------------------------------------

    /**
     * Hydrate the correct IrcObject subclass from a raw parsed API response.
     * Returns null if no matching subclass is found.
     */
    public static IrcObject fromResponse(Map<String, Object> body) {
        Object bt = body.get("base_target");
        if (bt == null) return null;
        String baseTarget = bt.toString().trim();
        if (baseTarget.isEmpty()) return null;

        // Determine prefix (first codepoint)
        int first = baseTarget.codePointAt(0);
        String prefix = new String(Character.toChars(first));

        return switch (prefix) {
            case "\u00A3" -> Network.fromBody(body);   // £
            case "#", "&" -> Channel.fromBody(body);
            case "@"      -> UserNick.fromBody(body);
            default       -> null;
        };
    }
}