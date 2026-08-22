package cx.ivc;

import java.util.Map;
import java.util.concurrent.CompletableFuture;

/**
 * AbstractIvcObject — abstract base class for all IVC IRC objects.
 * Implements IvcObject and provides mode parsing and caching.
 */
public abstract class AbstractIvcObject implements IvcObject {

    protected String rawModes = "";
    protected IvcModeSet modes = new IvcModeSet();
    protected IvcStatus lastStatus = null;

    // --- IvcObject implementations ---

    @Override
    public IvcModeSet modes() { return modes; }
    
    @Override
    public String prefix() {
        return target().substring(0, 1);
    }
    
    @Override
    public String object() {
        return target().substring(1);
    }

    @Override
    public String id() { return target(); }

    @Override
    public String host() { return null; }

    @Override
    public IvcUri uri() { return null; }

    @Override
    public Map<String, IvcObject> subobjects() { return Map.of(); }

    @Override
    public Map<String, String> props() { return Map.of(); }

    @Override
    public Map<String, Delta> events() { return Map.of(); }

    // --- Legacy / Helpers ---

    public abstract String target();

    public IvcModeSet parseModes(String raw) {
        this.rawModes = raw == null ? "" : raw;
        this.modes    = IvcModeSet.from(this.rawModes);
        return this.modes;
    }

    public String toModeString() {
        // Simple serialization of the mode set for backwards compatibility
        StringBuilder sb = new StringBuilder();
        for (IvcMode mode : modes) {
            sb.append(mode.mod() == IvcMode.Modifier.ADD ? '+' : (mode.mod() == IvcMode.Modifier.REMOVE ? '-' : '~'));
            sb.append(mode.name());
            if (mode.value() != null) {
                sb.append('=').append(mode.value());
            }
        }
        return sb.toString();
    }

    public IvcModeSet getModes() {
        return modes;
    }

    public boolean hasMode(String key) {
        for (IvcMode mode : modes) {
            if (mode.name().equals(key) && mode.mod() == IvcMode.Modifier.ADD) {
                return true;
            }
        }
        return false;
    }

    public String getProp(String key, String def) {
        for (IvcMode mode : modes) {
            if (mode.name().equals(key) && mode.mod() == IvcMode.Modifier.ADD) {
                return mode.value() != null ? mode.value() : def;
            }
        }
        return def;
    }

    public String getProp(String key) {
        return getProp(key, null);
    }

    public CompletableFuture<IvcResponse> setModes(String delta, String requester) {
        // Note: Currently calling the old IvcClient.applyModes which returns the old IvcResponse
        // We will update IvcClient to use the new models.
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

    public CompletableFuture<AbstractIvcObject> refresh() {
        return IvcClient.fetchObject(target()).thenApply(r -> {
            Object m = r.body().get("modes");
            if (m != null) parseModes(m.toString());
            if (r.status() != null) lastStatus = r.status();
            return this;
        });
    }

    public IvcStatus getLastStatus() { return lastStatus; }

    protected static <T extends AbstractIvcObject> T applyBody(T instance, Map<String, Object> body) {
        Object m = body.get("modes");
        if (m != null) instance.parseModes(m.toString());
        return instance;
    }
}
