<?php
error_reporting(-1);

require_once __DIR__.'/vendor/autoload.php';

$app = new Slim\App([
    'db' => function ($c) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=hello_world;charset=utf8', 
            'benchmarkdbuser', 
            'benchmarkdbpass',
            [
                PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true
            ]
        );

        return $pdo;
    },

    'view' => function ($c) {
        return new Slim\Views\PhpRenderer("templates/");
    }
]);

// Test 1: Plaintext
$app->get('/plaintext', function ($request, $response) {
    return $response
        ->write('Hello, World!')
        ->withHeader('Content-Type', 'text/plain')
        ;
});

// Test 2: JSON serialization
$app->get('/json', function ($request, $response) {
    return $response
        ->withJson(['message' => 'Hello, World!'])
        ->withHeader('Content-Type', 'application/json') // fixes utf-8 warning
        ;
});

// Test 3: Single database query
$app->get('/db', function ($request, $response) {
    $sth = $this->db->prepare('SELECT * FROM World WHERE id = ?');
    $sth->execute([mt_rand(1, 10000)]);
    $world = $sth->fetch();
    # Cast fields to int so they don't get wrapped with quotes
    $world['id'] = (int) $world['id'];
    $world['randomNumber'] = (int) $world['randomNumber'];

    return $response
        ->withJson($world)
        ->withHeader('Content-Type', 'application/json') // fixes utf-8 warning
        ;
});

// Test 4: Multiple database queries
$app->get('/dbs', function ($request, $response) {
    $queries = max(1, min($request->getParam('queries'), 500));

    $sth = $this->db->prepare('SELECT * FROM World WHERE id = ?');
    $worlds = [];
    for ($i = 0; $i < $queries; ++$i) {
        $sth->execute([mt_rand(1, 10000)]);
        $world = $sth->fetch();
        # Cast fields to int so they don't get wrapped with quotes
        $world['id'] = (int) $world['id'];
        $world['randomNumber'] = (int) $world['randomNumber'];
        $worlds[] = $world;
    }

    return $response
        ->withJson($worlds)
        ->withHeader('Content-Type', 'application/json') // fixes utf-8 warning
        ;
});

// Test 5: Updates
$app->get('/updates', function ($request, $response) {
    $queries = max(1, min($request->getParam('queries'), 500));

    $sth = $this->db->prepare('SELECT * FROM World WHERE id = ?');
    $updateSth = $this->db->prepare('UPDATE World SET randomNumber = ? WHERE id = ?');

    $worlds = [];
    for ($i = 0; $i < $queries; ++$i) {
        $id = mt_rand(1, 10000);
        $random_number = mt_rand(1, 10000);
        $sth->execute([$id]);
        $world = $sth->fetch();
        # Cast fields to int so they don't get wrapped with quotes
        $world['id'] = (int) $world['id'];
        $world['randomNumber'] = $random_number;

        $updateSth->execute([$world['randomNumber'], $world['id']]);

        $worlds[] = $world;
    }

    return $response
        ->withJson($worlds)
        ->withHeader('Content-Type', 'application/json') // fixes utf-8 warning
        ;
});

// Test 6: Fortunes
$app->get('/fortunes', function ($request, $response) {
    $sth = $this->db->prepare('SELECT * FROM Fortune');
    $sth->execute();
    $fortunes = $sth->fetchAll();

    array_push($fortunes, [
        'id'=> 0, 
        'message' => 'Additional fortune added at request time.'
    ]);
    usort($fortunes, function($left, $right) {
        return strcmp($left['message'], $right['message']);
    });

    return $this->view->render($response, "fortunes.php", ["fortunes" => $fortunes]);
});

$app->run();
