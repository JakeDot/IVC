const net = require('net');

function connectClient(nick, port) {
    return new Promise((resolve) => {
        const client = net.createConnection({ port }, () => {
            client.write(`NICK ${nick}\r\n`);
            client.write(`USER ${nick} 0 * :${nick}\r\n`);
            resolve(client);
        });
        
        client.on('data', (data) => {
            console.log(`[${nick}] <- ${data.toString().trim()}`);
        });
    });
}

(async () => {
    const c1 = await connectClient('Client1', 6668);
    const c2 = await connectClient('Client2', 6668);
    const c3 = await connectClient('Client3', 6668);

    setTimeout(() => {
        console.log("C1 JOIN #c");
        c1.write("JOIN #c\r\n");
    }, 500);

    setTimeout(() => {
        console.log("C1 MODE #c +k=key");
        c1.write("MODE #c +k=key\r\n");
    }, 1000);

    setTimeout(() => {
        console.log("C4 CONNECT and JOIN #c without key");
        connectClient('Client4', 6668).then(c4 => {
            setTimeout(() => {
                c4.write("JOIN #c\r\n");
            }, 200);
        });
    }, 1500);
    
    setTimeout(() => {
        console.log("C4 JOIN #c+k=key");
        connectClient('Client5', 6668).then(c5 => {
            setTimeout(() => {
                c5.write("JOIN #c+k=key\r\n");
            }, 200);
        });
    }, 2500);
    
    setTimeout(() => {
        process.exit(0);
    }, 4000);
})();
