--TEST--
MongoDB\Driver\Session test: Manager::executeWriteCommand pins transaction to server
--SKIPIF--
<?php require __DIR__ . "/../utils/basic-skipif.inc"; ?>
<?php skip_if_not_mongos_with_replica_set(); ?>
<?php skip_if_server_version('<', '4.1.6'); ?>
<?php skip_if_not_clean(); ?>
--FILE--
<?php
require_once __DIR__ . "/../utils/basic.inc";

$manager = new MongoDB\Driver\Manager(URI);

/* Create collections as that can't be (automatically) done in a transaction */
$manager->executeReadWriteCommand(
    DATABASE_NAME,
    new \MongoDB\Driver\Command([ 'create' => COLLECTION_NAME ]),
    [ 'writeConcern' => new \MongoDB\Driver\WriteConcern( \MongoDB\Driver\WriteConcern::MAJORITY ) ]
);

$servers = $manager->getServers();
$selectedServer = array_pop($servers);
$wrongServer = array_pop($servers);
var_dump($selectedServer != $wrongServer);

$session = $manager->startSession();
var_dump($session->getServer() instanceof \MongoDB\Driver\Server);

$session->startTransaction();
var_dump($session->getServer() instanceof \MongoDB\Driver\Server);

$command = new MongoDB\Driver\Command([
    'findAndModify' => COLLECTION_NAME,
    'query' => ['_id' => 'foo'],
    'upsert' => true,
    'new' => true,
    'update' => ['x' => 1]
]);
$selectedServer->executeWriteCommand(DATABASE_NAME, $command, ['session' => $session]);

var_dump($session->getServer() instanceof \MongoDB\Driver\Server);

$bulk = new MongoDB\Driver\BulkWrite();
$bulk->insert(['x' => 1]);
$selectedServer->executeBulkWrite(NS, $bulk, ['session' => $session]);

echo throws(function () use ($wrongServer, $session) {
    $command = new MongoDB\Driver\Command([
        'findAndModify' => COLLECTION_NAME,
        'query' => ['_id' => 'foo'],
        'upsert' => true,
        'new' => true,
        'update' => ['x' => 1]
    ]);
    $wrongServer->executeWriteCommand(DATABASE_NAME, $command, ['session' => $session]);
}, \MongoDB\Driver\Exception\RuntimeException::class), "\n";

$session->commitTransaction();

var_dump($session->getServer() instanceof \MongoDB\Driver\Server);

$bulk = new MongoDB\Driver\BulkWrite();
$bulk->insert(['x' => 1]);
$selectedServer->executeBulkWrite(NS, $bulk, ['session' => $session]);

var_dump($session->getServer() instanceof \MongoDB\Driver\Server);

?>
===DONE===
<?php exit(0); ?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
OK: Got MongoDB\Driver\Exception\RuntimeException
Requested server id does not matched pinned server id
bool(true)
bool(false)
===DONE===