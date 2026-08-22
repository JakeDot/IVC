package cx.ivc;

public record IvcUri(String url) implements IIvcUri {
    public static IvcUri parse(String value) {
        return new IvcUri(value);
    }

    public String object() {
        String authority = uri().getRawAuthority();
        if (authority == null || authority.isEmpty()) {
            throw new IllegalStateException("IVC URI has no object: " + uri());
        }
        return authority;
    }

    public char prefix() {
        return object().charAt(0);
    }
}
