package ivc;

import java.util.LinkedHashMap;
import java.util.Map;
import java.util.concurrent.CompletableFuture;

/**
 * IrcObject — abstract base class for all IVC IRC objects (Java 17 port).
 *
 * Mirrors PHP Fortress\IRC\IrcObject and TypeScript IvcObject.
 *
 * Provides:
 *   - Local mode string parsing / serialisation (via IvcMarshaller)
 *   - setModes() — sends a mode delta to the server and merges the result
 *   - getModes()  — returns the cached mode map
 *   - refresh()   — re-fetches the object from the server
 */
public abstract class IrcObject {

    /** Raw packed mode string as last received from server. */
    protected String rawModes = "";

    /** Parsed mode map. Values are ModeEntry or Boolean. */
    protected Map<String, Object> modes = new LinkedHashMap<>();

    /** IVC Status header from the last server response. */
    protected IvcStatus lastStatus = null;

    // ---------------------------------------------------------------
    // Abstract contract
    // ---------------------------------------------------------------

    /** Canonical IVC target string (e.g. "#fortress", "£", "@CyberFox"). */
    public abstract String target();

    // ---------------------------------------------------------------
    // Mode codec
    // ---------------------------------------------------------------

    /** Parse a raw mode string and cache the result. */
    public Map<String, Object> parseModes(String raw) {
        this.rawModes = raw == null ? "" : raw;
        this.modes    = IvcMarshaller.parseModeString(this.rawModes);
        return this.modes;
    }

    /** Serialise the cached mode map back to a packed mode string. */
    public String toModeString() {
        return IvcMarshaller.toModeString(this.modes);
    }

    /** Return the currently cached mode map (unmodifiable view). */
    public Map<String, Object> getModes() {
        return Map.copyOf(modes);
    }

    /** True if a boolean flag mode is currently set. */
    public boolean hasMode(String key) {
        Object entry = modes.get(key);
        if (entry == null)             return false;
        if (entry instanceof Boolean b) return b;
        if (entry instanceof ModeEntry me) return me.isSet();
        return false;
    }

    /**
     * Get the value of a §key=value property, or null/default if not set.
     */
    public String getProp(String key, String def) {
        Object entry = modes.get(key);
        if (!(entry instanceof ModeEntry me)) return def;
        return me.isSet() ? (me.val() != null ? me.val() : def) : def;
    }

    public String getProp(String key) {
        return getProp(key, null);
    }

    // ---------------------------------------------------------------
    // Server interaction
    // ---------------------------------------------------------------

    /**
     * Send a mode delta to the server and update local state on success.
     *
     *   channel.setModes("+§topic=Hello World").join();
     *   network.setModes("-§motd").join();
     */
    public CompletableFuture<IvcResponse> setModes(String delta, String requester) {
        return IvcClient.applyModes(target(), delta, requester == null ? "" : requester)
                .thenApply(res -> {
                    if (res.success() && res.modes() != null) parseModes(res.modes());
                    if (res.status() != null) lastStatus = res.status();
                    return res;
                });
    }

    public CompletableFuture<IvcResponse> setModes(String delta) {
        return setModes(delta, "");
    }

    /**
     * Re-fetch this object's mode state from the server.
     */
    public CompletableFuture<IrcObject> refresh() {
        return IvcClient.fetchObject(target()).thenApply(r -> {
            Object m = r.body().get("modes");
            if (m != null) parseModes(m.toString());
            if (r.status() != null) lastStatus = r.status();
            return this;
        });
    }

    public IvcStatus getLastStatus() { return lastStatus; }

    // ---------------------------------------------------------------
    // Factory helper called by subclasses
    // ---------------------------------------------------------------

    protected static <T extends IrcObject> T applyBody(T instance, Map<String, Object> body) {
        Object m = body.get("modes");
        if (m != null) instance.parseModes(m.toString());
        return instance;
    }
}