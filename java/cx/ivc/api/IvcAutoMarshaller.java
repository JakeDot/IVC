package cx.ivc.api;

import cx.ivc.models.api.IvcSignalResponse;
import cx.ivc.models.api.IvcForeignServiceResponse;
import cx.ivc.models.IvcParsedObject;

import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.List;
import java.util.ArrayList;

/**
 * A native, zero-dependency lightweight JSON auto-marshaller for IVC API responses.
 * Since this is a vanilla Java project without Maven/Gradle dependencies, we use native parsing.
 */
public class IvcAutoMarshaller {

    public static IvcSignalResponse parseSignalResponse(String json) {
        String status = extractString(json, "\"status\"\\s*:\\s*\"([^\"]+)\"");
        String csrf_token = extractString(json, "\"csrf_token\"\\s*:\\s*\"([^\"]+)\"");
        String error = extractString(json, "\"error\"\\s*:\\s*\"([^\"]+)\"");

        List<String> peers = new ArrayList<>();
        Matcher peerMatcher = Pattern.compile("\"peers\"\\s*:\\s*\\[(.*?)\\]", Pattern.DOTALL).matcher(json);
        if (peerMatcher.find()) {
            String peersArr = peerMatcher.group(1);
            Matcher m = Pattern.compile("\"([^\"]+)\"").matcher(peersArr);
            while (m.find()) {
                peers.add(m.group(1));
            }
        }

        List<IvcSignalResponse.SignalMessage> messages = new ArrayList<>();
        // Lightweight parsing of message array could be added here if needed.

        return new IvcSignalResponse(status, peers, messages, csrf_token, error);
    }

    public static IvcForeignServiceResponse parseForeignServiceResponse(String json) {
        String status = extractString(json, "\"status\"\\s*:\\s*\"([^\"]+)\"");
        String message = extractString(json, "\"message\"\\s*:\\s*\"([^\"]+)\"");
        String error = extractString(json, "\"error\"\\s*:\\s*\"([^\"]+)\"");

        return new IvcForeignServiceResponse(status, message, error, null);
    }

    private static String extractString(String json, String regex) {
        if (json == null) return null;
        Matcher matcher = Pattern.compile(regex).matcher(json);
        if (matcher.find()) {
            return matcher.group(1);
        }
        return null;
    }
}
