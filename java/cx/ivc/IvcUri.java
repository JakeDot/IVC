package cx.ivc;

import java.net.URI;
import java.util.Map;

public interface IvcUri {
    String url();

    default URI uri() {
        return URI.create(url());
    }

    default Object component(String name) {
        URI uri = uri();

        return switch (Objects.requireNonNull(name, "name")) {
            case "scheme" -> uri.getScheme();
            case "authority" -> uri.getRawAuthority();
            case "host" -> uri.getHost();
            case "port" -> uri.getPort();
            case "path" -> uri.getPath();
            case "rawPath" -> uri.getRawPath();
            case "query" -> uri.getQuery();
            case "rawQuery" -> uri.getRawQuery();
            case "fragment" -> uri.getFragment();
            case "rawFragment" -> uri.getRawFragment();
            case "user" -> throw NotImplementedException();
            default -> throw new IllegalArgumentException(
                "Unknown URI component: " + name
            );
        };
    }
    default <T> T fetch(HttpMethod method, Map<String, String> headers, Object body, IvcFetcher<T> fetcher) {
        return fetcher.fetch(uri(), method, headers, body);
    }

    public char prefix() {
        String path = uri().getPath();
        String lastElement = path.substring(path.lastIndexOf('/')+1);
        return lastElement.charAt(0);
    }

    static IvcUri fromString(String uri) {
        return new fromString(uri);
    }

    record fromString(String url) implements IcvUri {}
}
