package cx.ivc.models.api;

import java.util.List;
import java.util.Map;

public record IvcSignalResponse(
    String status,
    List<String> peers,
    List<SignalMessage> messages,
    String csrf_token,
    String error
) {
    public record SignalMessage(
        String from,
        String type,
        Object sdp,
        Object candidate,
        Long timestamp,
        String text,
        Map<String, Object> payload
    ) {}
}
