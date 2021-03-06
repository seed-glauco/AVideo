<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Socket\Message;

require_once dirname(__FILE__) . '/../../videos/configuration.php';
require_once $global['systemRootPath'] . 'plugin/Socket/Message.php';
require_once $global['systemRootPath'] . 'objects/autoload.php';

if (!isCommandLineInterface()) {
    die("Command line only");
}

$obj = AVideoPlugin::getDataObject("Socket");
ob_end_flush();
_mysql_close();
session_write_close();

_error_log("Starting Socket server at port {$obj->port}");

$scheme = parse_url($global['webSiteRootURL'], PHP_URL_SCHEME);
if(strtolower($scheme)!=='https'){
    $server = IoServer::factory(
                    new HttpServer(
                            new WsServer(
                                    new Message()
                            )
                    ),
                    $obj->port
    );

    $server->run();
} else {
    $parameters = [
        'local_cert' => $obj->server_crt_file,
        'local_pk' => $obj->server_key_file,
        'allow_self_signed' => $obj->allow_self_signed, // Allow self signed certs (should be false in production)
        'verify_peer' => false
    ];
    
    echo "Server Parameters ".json_encode($parameters).PHP_EOL;
    echo "Starting server on port {$obj->port}".PHP_EOL;
    
    $loop = React\EventLoop\Factory::create();
// Set up our WebSocket server for clients wanting real-time updates
    $webSock = new React\Socket\Server('0.0.0.0:' . $obj->port, $loop);
    $webSock = new React\Socket\SecureServer($webSock, $loop, $parameters);
    $webServer = new Ratchet\Server\IoServer(
            new HttpServer(
                    new WsServer(
                            new Message()
                    )
            ),
            $webSock
    );

    $loop->run();
}