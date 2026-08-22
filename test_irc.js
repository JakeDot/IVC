const net = require('net');

const client = new net.Socket();
client.connect(6667, '127.0.0.1', function() {
    console.log('Connected');
    client.write('NICK TestUser\r\n');
    client.write('USER TestUser 0 * :Test\r\n');
    setTimeout(() => {
        console.log('Sending PRIVMSG chanserv :help');
        client.write('PRIVMSG chanserv :help\r\n');
    }, 1000);
    setTimeout(() => {
        client.destroy();
    }, 3000);
});

client.on('data', function(data) {
    console.log('Received: ' + data);
});

client.on('close', function() {
    console.log('Connection closed');
});
