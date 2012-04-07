<?php

require('libs/Slim/Slim.php');
require('libs/Views/TwigView.php');

TwigView::$twigDirectory = __DIR__ . '/libs/Twig/';

$app = new Slim(array(
  'view' => new TwigView
));

$db = new PDO('mysql:host=localhost;dbname=snippets', 'root', 'password');

/*****************************************
 * Index
 *****************************************/

$app->get('/', function () use ($app, $db) {
  $qry = $app->request()->get('q');

  if ($qry) {
    $st = $db->prepare('SELECT *, MATCH (title, description, tags) AGAINST (:query IN BOOLEAN MODE) AS score ' .
                       'FROM snippet ' . 
                       'WHERE MATCH (title, description, tags) AGAINST (:query IN BOOLEAN MODE) ' . 
                       'ORDER BY score DESC');
    $st->execute(array(':query' => $qry));
  } else {
    $st = $db->query('SELECT * ' . 
                     'FROM snippet ' .
                     'ORDER BY RAND() ' .
                     'LIMIT 6');
  }

  $st->setFetchMode(PDO::FETCH_ASSOC);
  $results = $st->fetchAll();

  foreach($results as $k => $v) {
    $results[$k]['tags'] = explode(',', $results[$k]['tags']);
    if (strlen($results[$k]['description']) > 150)
      $results[$k]['description'] = substr($results[$k]['description'], 0, 150) . '...';
  }

  $app->render('list.html', array('snippets' => $results, 
                                  'alert'    => @$_SESSION['flash']['alert'],
                                  'query'    => $qry,
                                  'page'     => 'index'));
});

/*****************************************
 * View
 *****************************************/

$app->get('/view/:id', function ($id) use ($app, $db) {
  $st = $db->prepare('SELECT * FROM snippet WHERE id = :id LIMIT 1');
  $st->execute(array(':id' => $id));
  $st->setFetchMode(PDO::FETCH_ASSOC);
  $result = $st->fetch();
  $result['tags'] = explode(',', $result['tags']);

  if ($result) {
    $app->render('view.html', array('snippet' => $result,
                                    'page'    => 'view',
                                    'id'      => $id));
  } else {
    $app->flash('alert', 'An invalid snippet id was entered.');
    $app->redirect('/');
  }
})->conditions(array('id' => '[0-9]+')); // only allow valid ids through

/*****************************************
 * Add
 *****************************************/

$app->map('/add', function () use ($app, $db) {
  if ($app->request()->post()) {
    // convert post key-values to vars
    foreach ($app->request()->post() as $k => $v)
      $$k = $v;

    if (@$title && @$tags && @$description && @$snippet) {
      $st = $db->prepare('INSERT INTO snippet (title, description, snippet, tags) VALUES ' .
                         '(:title, :description, :snippet, :tags)');
      $st->execute(array(
        ':title'       => $title,
        ':description' => $description,
        ':snippet'     => html_entity_decode($snippet),
        ':tags'        => $tags
      ));

      $app->flash('alert', 'Successfully added new snippet.');
      $app->redirect('/');
    } else {
      $app->flash('alert', 'All fields are required.');
    }
  }   

  $app->render('add.html', array(
    'alert'       => @$_SESSION['flash']['alert'],
    'title'       => @$title,
    'tags'        => @$tags,
    'description' => @$description,
    'snippet'     => @$snippet,
    'page'        => 'add'
  ));
})->via('GET', 'POST');

/*****************************************
 * Edit
 *****************************************/

$app->map('/edit/:id', function ($id) use ($app, $db) {
  if ($app->request()->post()) {
    // convert post key-values to vars
    foreach ($app->request()->post() as $k => $v)
      $$k = $v;

    if (@$title && @$tags && @$description && @$snippet) {
      $st = $db->prepare('UPDATE snippet ' .
                         'SET title = :title, description = :description, snippet = :snippet, tags = :tags ' .
                         'WHERE id = :id');
      $st->execute(array(
        ':title'       => $title,
        ':description' => $description,
        ':snippet'     => html_entity_decode($snippet),
        ':tags'        => $tags,
        ':id'          => $id
      ));

      $app->flash('alert', 'Successfully updated the snippet.');
      $app->redirect('/');
    } else {
      $app->flash('alert', 'All fields are required.');
    }
  }

  $st = $db->prepare('SELECT * FROM snippet WHERE id = :id');
  $st->execute(array(':id' => $id));
  $st->setFetchMode(PDO::FETCH_ASSOC);
  $result = $st->fetch();

  if ($result) {
    $app->render('edit.html', array(
      'alert'       => @$_SESSION['flash']['alert'],
      'snippet'     => $result,
      'page'        => 'edit'
    ));
  } else {
    $app->flash('alert', 'An invalid snippet id was entered.');
    $app->redirect('/');
  }
})->via('GET', 'POST')->conditions(array('id' => '[0-9]+')); // only allow valid ids through;

/*****************************************
 * Let it roll...
 *****************************************/

$app->run();

$db = NULL; // close db connection