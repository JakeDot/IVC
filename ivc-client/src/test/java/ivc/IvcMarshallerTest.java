package ivc;

import org.junit.jupiter.api.Test;
import java.util.Map;
import static org.junit.jupiter.api.Assertions.*;

class IvcMarshallerTest {

    @Test
    void parseModeString_flagModes() {
        Map<String, Object> m = IvcMarshaller.parseModeString("+o-t");
        assertTrue(((ModeEntry) m.get("o")).plus(), "+o should be plus");
        assertTrue(((ModeEntry) m.get("t")).minus(), "-t should be minus");
    }

    @Test
    void parseModeString_keyValueProps() {
        Map<String, Object> m = IvcMarshaller.parseModeString("+\u00A7maxchans=500+\u00A7motd=Welcome");
        ModeEntry mc = (ModeEntry) m.get("\u00A7maxchans");
        assertNotNull(mc);
        assertEquals("500", mc.val());
        assertTrue(mc.isSet());

        ModeEntry motd = (ModeEntry) m.get("\u00A7motd");
        assertEquals("Welcome", motd.val());
    }

    @Test
    void toModeString_roundTrip() {
        String raw    = "+\u00A7motd=Hello+o-t";
        Map<String, Object> m = IvcMarshaller.parseModeString(raw);
        String packed = IvcMarshaller.toModeString(m);
        // Re-parse and compare values
        Map<String, Object> m2 = IvcMarshaller.parseModeString(packed);
        assertEquals(((ModeEntry) m.get("\u00A7motd")).val(), ((ModeEntry) m2.get("\u00A7motd")).val());
        assertEquals(((ModeEntry) m.get("o")).plus(),         ((ModeEntry) m2.get("o")).plus());
        assertEquals(((ModeEntry) m.get("t")).minus(),        ((ModeEntry) m2.get("t")).minus());
    }

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
        IrcObject obj = IvcMarshaller.fromResponse(body);
        assertInstanceOf(Network.class, obj);
        assertEquals("Hello", ((Network) obj).get("\u00A7motd"));
    }
}