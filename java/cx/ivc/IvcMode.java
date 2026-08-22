package cx.ivc;

public record IvcMode(String name, Object value, Modifier mod){
    public record Modifier(char prefix) {
        public Modifier {
            if ("+-~".indexOf(prefix) == -1) {
                throw errorModifier(prefix);
            }
        }
        
        public static final Modifier
        ADD = new Modifier('+'),
        REMOVE = new Modifier('-'),
        UNSET = new Modifier('~');
        
        public static IllegalArgumentException errorModifier(char prefix) {
            return new IllegalArgumentException("Invalid Modifier prefix: " + prefix);
        }
    }

    public static Modifier mod(char c) {
        return switch (c) {
            case '+' -> Modifier.ADD;
            case '-' -> Modifier.REMOVE;
            case '~' -> Modifier.UNSET;
            default -> throw Modifier.errorModifier(c);
        };
    }

    public static final IvcMode EMPTY = new IvcMode("", null, Modifier.UNSET);

    public static IvcMode from(String part, char defaultPrefix) {
        if (part == null || part.trim().isEmpty()) {
            return EMPTY;
        }
        part = part.trim();
        char p = part.charAt(0);
        Modifier modifier;
        String rest;
        
        if (p == '+' || p == '-' || p == '~') {
            modifier = mod(p);
            rest = part.substring(1);
        } else {
            modifier = mod(defaultPrefix);
            rest = part;
        }
        
        String name;
        String value = null;
        int eqIndex = rest.indexOf('=');
        if (eqIndex != -1) {
            name = rest.substring(0, eqIndex);
            value = rest.substring(eqIndex + 1);
        } else {
            name = rest;
        }
        
        if (name.isEmpty() && value == null && modifier == Modifier.UNSET) {
            return EMPTY;
        }
        
        return new IvcMode(name, value, modifier);
    }
}
