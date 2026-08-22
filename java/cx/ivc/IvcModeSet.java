package cx.ivc;

import java.util.Collection;
import java.util.HashSet;
import java.util.stream.Collectors;

public class IvcModeSet extends HashSet<IvcMode> {

    public static IvcModeSet from(String modeString) {
        if (modeString == null || modeString.trim().isEmpty()) {
            return new IvcModeSet();
        }

        char prefix = modeString.trim().charAt(0);
        boolean valid = switch(prefix) {
            case '+', '-', '~' -> true;
            default -> false;
        };

        if (valid) {
            IvcModeSet set = new IvcModeSet();
            // Split on optional commas OR the zero-width boundary right before a +, -, or ~
            String[] parts = modeString.split(",|(?=[+\\-~])");
            for (String part : parts) {
                part = part.trim();
                if (part.isEmpty()) continue;
                set.add(IvcMode.from(part, prefix));
            }
            return set;
        } else {
            throw IvcMode.Modifier.errorModifier(prefix);
        }
    }
    
    public IvcModeSet() {
        super();
    }
    
    public IvcModeSet(Collection<? extends IvcMode> c) {
        super();
        this.addAll(c);
    }

    @Override
    public boolean add(IvcMode mode) {
        if (mode == null || IvcMode.EMPTY.equals(mode)) {
            return false;
        }
        return super.add(mode);
    }

    public IvcModeSet added() {
        return filterBy(IvcMode.Modifier.ADD);
    }
    
    public IvcModeSet removed() {
        return filterBy(IvcMode.Modifier.REMOVE);
    }
    
    public IvcModeSet unset() {
        return filterBy(IvcMode.Modifier.UNSET);
    }

    public IvcModeSet filterBy(char c) {
        return filterBy(IvcMode.mod(c));
    }

    private IvcModeSet filterBy(IvcMode.Modifier mod) {
        return this.stream()
                .filter(m->m.mod().equals(mod))
                .collect(Collectors.toCollection(IvcModeSet::new));
    }
}
