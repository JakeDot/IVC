package cx.ivc;

import java.net.URI;
import java.util.Map;

@FunctionalInterface
public interface IvcFetcher<T> {
    T fetch(URI uri, HttpMethod method, Map<String, String> headers, Object body);
}
