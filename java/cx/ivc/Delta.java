package cx.ivc;

public record Delta(String type, long timestamp, String source, Object value) {}
