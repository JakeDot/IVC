package ivc;

import java.util.Map;
import java.util.concurrent.CompletableFuture;

/**
 * Channel — IVC channel object (#channel / &channel).
 *
 * Mirrors PHP Fortress\IRC\Serv\ChanServ object semantics.
 */
public class Channel extends IrcObject {

    private final String name;

    public Channel(String name) {
        this.name = normalize(name);
    }

    @Override
    public String target() { return name; }

    public String getName() { return name; }

    public static String normalize(String name) {
        name = name == null ? "" : name.trim();
        if (!name.startsWith("#") && !name.startsWith("&")) name = "#" + name;
        return name;
    }

    // --- Mode flag helpers ---

    public boolean isSecret()      { return hasMode("s"); }
    public boolean isModerated()   { return hasMode("m"); }
    public boolean isTopicLocked() { return hasMode("t"); }
    public boolean isInviteOnly()  { return hasMode("i"); }
    public String  passkey()       { return getProp("k"); }

    /** Check access for a user via the server. */
    public CompletableFuture<Map<String, Object>> checkAccess(String user) {
        return IvcClient.checkAccess(name, user).thenApply(r -> r.body());
    }

    // --- Factory / auto-marshalling ---

    public static Channel fromBody(Map<String, Object> body) {
        String bt  = body.getOrDefault("base_target", "").toString();
        Channel ch = new Channel(bt.isEmpty() ? "#unknown" : bt);
        return IrcObject.applyBody(ch, body);
    }

    /**
     * Fetch a channel object from the server and return a hydrated Channel.
     *
     *   Channel chan = Channel.fromServer("#fortress").join();
     *   System.out.println(chan.isModerated());
     */
    public static CompletableFuture<Channel> fromServer(String name) {
        String norm = normalize(name);
        return IvcClient.fetchObject(norm).thenApply(r -> {
            Map<String, Object> body = new java.util.LinkedHashMap<>(r.body());
            body.putIfAbsent("base_target", norm);
            Channel ch = fromBody(body);
            if (r.status() != null) ch.lastStatus = r.status();
            return ch;
        });
    }
}