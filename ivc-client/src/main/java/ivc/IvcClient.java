package ivc;

import java.io.IOException;
import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.Map;
import java.util.Optional;
import java.util.concurrent.CompletableFuture;

/**
 * IvcClient — HTTP transport layer for the IVC API (Java 17 / JDK HttpClient).
 *
 * Configuration:
 *   IvcClient.configure("https://my.ivc.host");
 *
 * Defaults (in order):
 *   1. Last value passed to configure()
 *   2. System property ivc.base.url
 *   3. Environment variable IVC_BASE_URL
 *   4. "" (relative — caller must set explicitly)
 */
public final class IvcClient {

    private IvcClient() {}

    private static String _baseUrl = resolveDefaultBaseUrl();
    private static Duration _timeout = Duration.ofSeconds(10);

    private static final HttpClient HTTP = HttpClient.newBuilder()
            .version(HttpClient.Version.HTTP_1_1)
            .connectTimeout(Duration.ofSeconds(5))
            .build();

    // ---------------------------------------------------------------
    // Configuration
    // ---------------------------------------------------------------

    public static void configure(String baseUrl) {
        _baseUrl = baseUrl.replaceAll("/$", "");
    }

    public static void configure(String baseUrl, Duration timeout) {
        configure(baseUrl);
        _timeout = timeout;
    }

    private static String resolveDefaultBaseUrl() {
        String prop = System.getProperty("ivc.base.url");
        if (prop != null && !prop.isBlank()) return prop.replaceAll("/$", "");
        String env  = System.getenv("IVC_BASE_URL");
        if (env  != null && !env.isBlank())  return env.replaceAll("/$", "");
        return "";
    }

    // ---------------------------------------------------------------
    // Raw HTTP helpers
    // ---------------------------------------------------------------

    public static CompletableFuture<IvcRawResult> get(String path) {
        HttpRequest req = HttpRequest.newBuilder()
                .uri(URI.create(_baseUrl + path))
                .timeout(_timeout)
                .header("X-IVC-Client", "java/1.0")
                .GET()
                .build();
        return HTTP.sendAsync(req, HttpResponse.BodyHandlers.ofString())
                .thenApply(IvcClient::processResponse);
    }

    public static CompletableFuture<IvcRawResult> post(String path, String jsonBody) {
        HttpRequest req = HttpRequest.newBuilder()
                .uri(URI.create(_baseUrl + path))
                .timeout(_timeout)
                .header("Content-Type", "application/json")
                .header("X-IVC-Client", "java/1.0")
                .POST(HttpRequest.BodyPublishers.ofString(jsonBody, StandardCharsets.UTF_8))
                .build();
        return HTTP.sendAsync(req, HttpResponse.BodyHandlers.ofString())
                .thenApply(IvcClient::processResponse);
    }

    public static CompletableFuture<IvcRawResult> put(String path, String jsonBody) {
        HttpRequest req = HttpRequest.newBuilder()
                .uri(URI.create(_baseUrl + path))
                .timeout(_timeout)
                .header("Content-Type", "application/json")
                .header("X-IVC-Client", "java/1.0")
                .PUT(HttpRequest.BodyPublishers.ofString(jsonBody, StandardCharsets.UTF_8))
                .build();
        return HTTP.sendAsync(req, HttpResponse.BodyHandlers.ofString())
                .thenApply(IvcClient::processResponse);
    }

    private static IvcRawResult processResponse(HttpResponse<String> res) {
        String raw = res.body();
        Map<String, Object> body = JsonSimple.parse(raw);

        // Parse Status: header
        IvcStatus status = null;
        Optional<String> sh = res.headers().firstValue("Status");
        if (sh.isEmpty()) sh = res.headers().firstValue("X-IVC-Status");
        if (sh.isPresent()) status = IvcMarshaller.parseStatusHeader(sh.get());

        IrcObject object = IvcMarshaller.fromResponse(body);
        return new IvcRawResult(body, status, object);
    }

    // ---------------------------------------------------------------
    // IVC-specific helpers
    // ---------------------------------------------------------------

    /**
     * Fetch the current mode string for a target object from the server.
     * Endpoint: GET /api/object.php?target=<target>
     */
    public static CompletableFuture<IvcRawResult> fetchObject(String target) {
        String encoded = URLEncoder.encode(target, StandardCharsets.UTF_8);
        return get("/api/object.php?target=" + encoded);
    }

    /**
     * Apply a mode delta to a target object.
     * Endpoint: POST /api/modes.php
     */
    public static CompletableFuture<IvcResponse> applyModes(String target, String delta, String requester) {
        String json = String.format(
                "{\"target\":\"%s\",\"delta\":\"%s\",\"requester\":\"%s\"}",
                escape(target), escape(delta), escape(requester));
        return post("/api/modes.php", json).thenApply(r -> {
            boolean success = Boolean.TRUE.equals(r.body().get("success"));
            String  message = (String) r.body().getOrDefault("message", "");
            String  bt      = (String) r.body().get("base_target");
            String  modes   = (String) r.body().get("modes");
            IvcResponse res = new IvcResponse(success, message, bt, modes, r.status());
            return res;
        });
    }

    /**
     * Check access to a target.
     * Endpoint: GET /api/access.php?target=<uri>&user=<nick>
     */
    public static CompletableFuture<IvcRawResult> checkAccess(String target, String user) {
        String path = "/api/access.php?target=" + URLEncoder.encode(target, StandardCharsets.UTF_8);
        if (user != null && !user.isBlank()) {
            path += "&user=" + URLEncoder.encode(user, StandardCharsets.UTF_8);
        }
        return get(path);
    }

    private static String escape(String s) {
        return s == null ? "" : s.replace("\\", "\\\\").replace("\"", "\\\"");
    }

    // ---------------------------------------------------------------
    // Raw result container
    // ---------------------------------------------------------------

    public record IvcRawResult(Map<String, Object> body, IvcStatus status, IrcObject object) {}
}