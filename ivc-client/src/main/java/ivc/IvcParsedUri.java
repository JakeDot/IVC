package ivc;

import java.util.Map;

/**
 * IvcParsedUri — result of parsing an ivc:// URI.
 * Mirrors TypeScript IvcParsedUri.
 */
public record IvcParsedUri(
        String scheme,
        String host,
        String prefix,
        String target,
        String modes,
        Map<String, String> props,
        Map<String, String> events,
        String raw
) {
    /** Full base target with prefix, e.g. "#fortress". */
    public String fullTarget() { return prefix + target; }
}