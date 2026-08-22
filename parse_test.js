function parseRoomId(raw) {
    let base = raw;
    let key = null;
    const pos = Math.max(raw.indexOf('+'), raw.indexOf('-'));
    if (pos > -1) {
        base = raw.substring(0, pos);
        const modes = raw.substring(pos);
        const kMatch = modes.match(/\+k=([^+-]+)/);
        if (kMatch) {
            key = kMatch[1];
        }
    }
    return { base, key };
}
console.log(parseRoomId("#c"));
console.log(parseRoomId("#c+k=key"));
