package cx.ivc;

import org.junit.jupiter.api.Test;
import java.util.Map;
import static org.junit.jupiter.api.Assertions.*;

class IvcMarshallerTest {


    @Test
    void parseStatusHeader_fullFormat() {
        IvcStatus s = IvcMarshaller.parseStatusHeader("200+modes:CyberFox{subs [#room+t+o, #lobby]}");
        assertEquals(200,       s.httpCode());
        assertEquals("CyberFox", s.nick());
        assertEquals(2,          s.targets().size());
        assertEquals("#room",    s.targets().get(0).name());
        assertEquals("+t+o",     s.targets().get(0).modes());
        assertEquals("#lobby",   s.targets().get(1).name());
    }

    @Test
    void parseUri_channelWithModes() {
        IvcParsedUri uri = IvcMarshaller.parseUri("ivc://local.host/#fortress+ov");
        assertEquals("ivc",      uri.scheme());
        assertEquals("local.host", uri.host());
        assertEquals("#",        uri.prefix());
        assertEquals("fortress", uri.target());
        assertEquals("+ov",      uri.modes());
    }

    @Test
    void fromResponse_returnsNetwork() {
        Map<String, Object> body = Map.of(
                "success",     true,
                "base_target", "\u00A3",
                "modes",       "+\u00A7motd=Hello"
        );
        AbstractIvcObject obj = IvcMarshaller.fromResponse(body);
        assertInstanceOf(Network.class, obj);
        assertEquals("Hello", ((Network) obj).get("\u00A7motd"));
    }
}
