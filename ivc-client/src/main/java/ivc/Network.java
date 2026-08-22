package ivc;

import java.util.LinkedHashMap;
import java.util.Map;
import java.util.concurrent.CompletableFuture;

/**
 * Network — global unnamed IRC object (£).
 *
 * Mirrors PHP Fortress\IRC\Objects\Network and TypeScript Network.
 *
 * Server-wide configuration is stored as a single mode string under
 * the key £, e.g.: +§maxchans=500+§motd=Welcome+§flood_limit=10
 *
 * Usage:
 *   Network net = Network.fromServer().join();
 *   String motd = net.get("§motd");
 *   net.set("§motd", "Hello", operNick).join();
 *   Map<String,String> all = net.all();
 */
public class Network extends IrcObject {

    public static final String OBJECT_KEY = "\u00A3"; // £

    @Override
    public String target() { return OBJECT_KEY; }

    // ---------------------------------------------------------------
    // Convenience property API
    // ---------------------------------------------------------------

    /** Get a server property from the locally cached mode map. */
    public String get(String key) {
        return getProp(key, null);
    }

    public String get(String key, String def) {
        return getProp(key, def);
    }

    /** Set a server property (IRCop enforced server-side). */
    public CompletableFuture<IvcResponse> set(String key, String value, String requester) {
        return setModes("+" + key + "=" + value, requester);
    }

    public static CompletableFuture<IvcResponse> setStatic(String key, String value, String requester) {
        return IvcClient.applyModes(OBJECT_KEY, "+" + key + "=" + value, requester);
    }

    /** Unset a server property. */
    public CompletableFuture<IvcResponse> unset(String key, String requester) {
        return setModes("-" + key, requester);
    }

    public static CompletableFuture<IvcResponse> unsetStatic(String key, String requester) {
        return IvcClient.applyModes(OBJECT_KEY, "-" + key, requester);
    }

    /** Return all active §key=value server properties as a plain map. */
    public Map<String, String> all() {
        Map<String, String> out = new LinkedHashMap<>();
        for (Map.Entry<String, Object> e : modes.entrySet()) {
            if (e.getValue() instanceof ModeEntry me && me.isSet() && me.val() != null) {
                out.put(e.getKey(), me.val());
            }
        }
        return Map.copyOf(out);
    }

    // ---------------------------------------------------------------
    // Factory / auto-marshalling
    // ---------------------------------------------------------------

    /** Called by IvcMarshaller.fromResponse() for £ targets. */
    public static Network fromBody(Map<String, Object> body) {
        return IrcObject.applyBody(new Network(), body);
    }

    /**
     * Fetch the £ object from the server and return a hydrated Network.
     *
     *   Network net = Network.fromServer().join();
     *   System.out.println(net.get("§motd"));
     */
    public static CompletableFuture<Network> fromServer() {
        return IvcClient.fetchObject(OBJECT_KEY).thenApply(r -> {
            Network net = fromBody(r.body());
            if (r.status() != null) net.lastStatus = r.status();
            return net;
        });
    }
}