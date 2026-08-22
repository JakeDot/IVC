package fortress.models;

import java.util.Map;
import java.util.Collection;

public non-sealed interface IvcObject {
    
    String host();
    String object();
    String id();
    String prefix();
    Collection<IvcMode> modes();
    IvcUri uri();
    Map<String, IvcObject> subobjects();
    Map<String, String> props();
    Map<String, Delta> events();
}
