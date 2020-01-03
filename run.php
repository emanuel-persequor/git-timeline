<?php

require_once ('lib/PSQR/Git/Repository.php');
require_once ('lib/PSQR/Graphviz/DiGraph.php');
require_once ('lib/PSQR/HTML/Timeline.php');

use PSQR\Git;

//$gitdir = ;
//$repo = new Git\Repository("/home/emanuel/Development/SAGA/saga/.git");
$repo = new Git\Repository("/home/emanuel/Development/saga-workbench/.git");
$repo->init(false);


/*
$digraph = new \PSQR\Graphviz\DiGraph($repo);

$digraph->generate();
$digraph->output("pdf", __DIR__.'/test.pdf');
*/

$html = new \PSQR\HTML\Timeline($repo);
$html->generate();
//$html->output(__DIR__.'/test.html');
$html->output();