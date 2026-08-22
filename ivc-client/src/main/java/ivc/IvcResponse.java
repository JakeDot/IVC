package ivc;

/**
 * IvcResponse — result of a mode-set or API call.
 * Mirrors PHP array{success, message, base_target, modes}.
 */
public record IvcResponse(boolean success, String message, String baseTarget, String modes, IvcStatus status) {

    public static IvcResponse ok(String message, String baseTarget, String modes) {
        return new IvcResponse(true, message, baseTarget, modes, null);
    }
    public static IvcResponse fail(String message) {
        return new IvcResponse(false, message, null, null, null);
    }
    public IvcResponse withStatus(IvcStatus s) {
        return new IvcResponse(success, message, baseTarget, modes, s);
    }
}