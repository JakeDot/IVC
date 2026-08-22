package cx.ivc;

import java.net.URI;
import java.util.Map;

public interface IIvcUri {
    String url();

    default URI uri() {
        return URI.create(url());
    }

    default <T> T fetch(HttpMethod method, Map<String, String> headers, Object body, IvcFetcher<T> fetcher) {
        return fetcher.fetch(uri(), method, headers, body);
    }
}
