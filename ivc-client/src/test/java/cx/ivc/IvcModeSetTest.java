package cx.ivc;

import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

public class IvcModeSetTest {

    @Test
    public void testSuperEmptyModeString() {
        IvcModeSet set = IvcModeSet.from("~~~~~");
        
        // Since all parsed modes are just empty "~" modes, they should yield IvcMode.EMPTY
        // IvcModeSet's add() method explicitly ignores IvcMode.EMPTY
        // Therefore, the resulting set should be empty
        assertTrue(set.isEmpty(), "A super-empty mode string '~~~~~' should produce an empty IvcModeSet");
        assertEquals(0, set.size());
    }

    @Test
    public void testSingleModeParsing() {
        IvcModeSet set = IvcModeSet.from("+test=val");
        assertEquals(1, set.size());
        assertTrue(set.contains(new IvcMode("test", "val", IvcMode.Modifier.ADD)));
    }
    
    @Test
    public void testMultipleModesWithCommas() {
        IvcModeSet set = IvcModeSet.from("+a=1, -b=2, ~c");
        assertEquals(3, set.size());
        assertTrue(set.contains(new IvcMode("a", "1", IvcMode.Modifier.ADD)));
        assertTrue(set.contains(new IvcMode("b", "2", IvcMode.Modifier.REMOVE)));
        assertTrue(set.contains(new IvcMode("c", null, IvcMode.Modifier.UNSET)));
        
        assertEquals(1, set.added().size());
        assertEquals(1, set.removed().size());
        assertEquals(1, set.unset().size());
    }

    @Test
    public void testMultipleModesWithoutCommas() {
        IvcModeSet set = IvcModeSet.from("+a=1-b=2~c");
        assertEquals(3, set.size());
        assertTrue(set.contains(new IvcMode("a", "1", IvcMode.Modifier.ADD)));
        assertTrue(set.contains(new IvcMode("b", "2", IvcMode.Modifier.REMOVE)));
        assertTrue(set.contains(new IvcMode("c", null, IvcMode.Modifier.UNSET)));
    }

    @Test
    public void testFallbackModifier() {
        // Here 'b=2' and 'c=3' should inherit the '+' modifier from the beginning
        IvcModeSet set = IvcModeSet.from("+a=1,b=2,c=3");
        assertEquals(3, set.size());
        assertTrue(set.contains(new IvcMode("a", "1", IvcMode.Modifier.ADD)));
        assertTrue(set.contains(new IvcMode("b", "2", IvcMode.Modifier.ADD)));
        assertTrue(set.contains(new IvcMode("c", "3", IvcMode.Modifier.ADD)));
        
        assertEquals(3, set.added().size());
    }

    @Test
    public void testEmptyModeString() {
        assertTrue(IvcModeSet.from("").isEmpty());
        assertTrue(IvcModeSet.from("   ").isEmpty());
        assertTrue(IvcModeSet.from(null).isEmpty());
    }
}
