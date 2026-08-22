package ivc;

import java.util.*;

/**
 * JsonSimple — a minimal, zero-dependency JSON object parser.
 *
 * Supports the subset of JSON returned by IVC API endpoints:
 *   { "key": "value", "key2": true, "key3": 123 }
 *
 * For production use, replace with Gson, Jackson, or similar.
 */
public final class JsonSimple {

    private JsonSimple() {}

    /**
     * Parse a JSON object string into a Map<String, Object>.
     * Nested objects become Map<String, Object>; arrays become List<Object>.
     * Strings, booleans, and numbers are returned as their Java equivalents.
     *
     * Returns an empty map on parse error.
     */
    public static Map<String, Object> parse(String json) {
        if (json == null || json.isBlank()) return Map.of();
        try {
            json = json.trim();
            if (json.startsWith("{")) {
                return (Map<String, Object>) parseValue(json, new int[]{0});
            }
        } catch (Exception ignored) {}
        return new LinkedHashMap<>();
    }

    @SuppressWarnings("unchecked")
    private static Object parseValue(String s, int[] pos) {
        skipWhitespace(s, pos);
        if (pos[0] >= s.length()) return null;
        char ch = s.charAt(pos[0]);
        return switch (ch) {
            case '{' -> parseObject(s, pos);
            case '[' -> parseArray(s, pos);
            case '"' -> parseString(s, pos);
            case 't' -> { pos[0] += 4; yield Boolean.TRUE; }
            case 'f' -> { pos[0] += 5; yield Boolean.FALSE; }
            case 'n' -> { pos[0] += 4; yield null; }
            default  -> parseNumber(s, pos);
        };
    }

    private static Map<String, Object> parseObject(String s, int[] pos) {
        Map<String, Object> map = new LinkedHashMap<>();
        pos[0]++; // skip '{'
        skipWhitespace(s, pos);
        if (pos[0] < s.length() && s.charAt(pos[0]) == '}') { pos[0]++; return map; }
        while (pos[0] < s.length()) {
            skipWhitespace(s, pos);
            if (s.charAt(pos[0]) == '}') { pos[0]++; break; }
            String key = parseString(s, pos);
            skipWhitespace(s, pos);
            if (pos[0] < s.length() && s.charAt(pos[0]) == ':') pos[0]++;
            skipWhitespace(s, pos);
            Object val = parseValue(s, pos);
            map.put(key, val);
            skipWhitespace(s, pos);
            if (pos[0] < s.length() && s.charAt(pos[0]) == ',') pos[0]++;
        }
        return map;
    }

    private static List<Object> parseArray(String s, int[] pos) {
        List<Object> list = new ArrayList<>();
        pos[0]++; // skip '['
        skipWhitespace(s, pos);
        if (pos[0] < s.length() && s.charAt(pos[0]) == ']') { pos[0]++; return list; }
        while (pos[0] < s.length()) {
            skipWhitespace(s, pos);
            if (s.charAt(pos[0]) == ']') { pos[0]++; break; }
            list.add(parseValue(s, pos));
            skipWhitespace(s, pos);
            if (pos[0] < s.length() && s.charAt(pos[0]) == ',') pos[0]++;
        }
        return list;
    }

    private static String parseString(String s, int[] pos) {
        pos[0]++; // skip opening quote
        StringBuilder sb = new StringBuilder();
        while (pos[0] < s.length()) {
            char ch = s.charAt(pos[0]++);
            if (ch == '"') break;
            if (ch == '\\' && pos[0] < s.length()) {
                char esc = s.charAt(pos[0]++);
                sb.append(switch (esc) {
                    case 'n' -> '\n'; case 'r' -> '\r'; case 't' -> '\t';
                    case '"' -> '"'; case '\\' -> '\\'; default  -> esc;
                });
            } else {
                sb.append(ch);
            }
        }
        return sb.toString();
    }

    private static Number parseNumber(String s, int[] pos) {
        int start = pos[0];
        while (pos[0] < s.length() && "0123456789-.eE+".indexOf(s.charAt(pos[0])) != -1) pos[0]++;
        String num = s.substring(start, pos[0]);
        try {
            if (num.contains(".") || num.contains("e") || num.contains("E")) return Double.parseDouble(num);
            return Long.parseLong(num);
        } catch (NumberFormatException e) { return 0; }
    }

    private static void skipWhitespace(String s, int[] pos) {
        while (pos[0] < s.length() && Character.isWhitespace(s.charAt(pos[0]))) pos[0]++;
    }
}