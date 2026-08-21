package fortress.models;

import java.util.Map;
import java.util.List;

public sealed interface IvcObject permits IvcParsedObject {
    String scheme();
    String host();
    String object();
    String uri();
    List<Map<String, String>> subobjects();
    Map<String, Map<String, String>> props();
    Map<String, Map<String, String>> events();
    Map<String, String> asObject();
}
