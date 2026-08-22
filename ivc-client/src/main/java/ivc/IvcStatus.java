package ivc;

import java.util.List;

/**
 * IvcStatus — parsed Status: response header.
 *
 * Format: "200+modes:CyberFox{subs [#room+t+o]}"
 */
public record IvcStatus(int httpCode, String nick, List<Target> targets, String raw) {

    public record Target(String name, String modes) {}

    public static IvcStatus unknown() {
        return new IvcStatus(0, "", List.of(), "");
    }
}