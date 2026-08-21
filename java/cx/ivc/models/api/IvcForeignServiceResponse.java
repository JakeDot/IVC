package cx.ivc.models.api;

public record IvcForeignServiceResponse(
    String status,
    String message,
    String error,
    Object data
) {}
