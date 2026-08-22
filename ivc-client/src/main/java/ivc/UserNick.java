package ivc;

import java.util.Map;
import java.util.concurrent.CompletableFuture;

/**
 * UserNick — IVC user nickname object (@nick).
 *
 * Mirrors PHP Fortress\IRC\Serv\NameServ object semantics.
 */
public class UserNick extends IrcObject {

    private final String nick;

    public UserNick(String nick) {
        this.nick = nick.startsWith("@") ? nick.substring(1) : nick;
    }

    @Override
    public String target() { return "@" + nick; }

    public String getNick() { return nick; }

    public boolean isIdentified() {
        return hasMode("i") || "1".equals(getProp("§identified"));
    }

    public boolean isRegistered() {
        return hasMode("r");
    }

    public String getDomain() {
        return getProp("§domain");
    }

    public String getStandardizedUsername() {
        String d = getDomain();
        return d != null ? nick + "@" + d : nick;
    }

    // --- Factory / auto-marshalling ---

    public static UserNick fromBody(Map<String, Object> body) {
        String bt   = body.getOrDefault("base_target", "@unknown").toString();
        UserNick un = new UserNick(bt.replaceFirst("^@", ""));
        return IrcObject.applyBody(un, body);
    }

    /**
     * Fetch a user object from the server and return a hydrated UserNick.
     *
     *   UserNick user = UserNick.fromServer("CyberFox").join();
     *   System.out.println(user.getDomain());
     */
    public static CompletableFuture<UserNick> fromServer(String nick) {
        String target = nick.startsWith("@") ? nick : "@" + nick;
        return IvcClient.fetchObject(target).thenApply(r -> {
            Map<String, Object> body = new java.util.LinkedHashMap<>(r.body());
            body.putIfAbsent("base_target", target);
            UserNick un = fromBody(body);
            if (r.status() != null) un.lastStatus = r.status();
            return un;
        });
    }
}