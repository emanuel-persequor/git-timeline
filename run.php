<?php

require_once ('lib/PSQR/Git/Repository.php');
require_once ('lib/PSQR/Graphviz/DiGraph.php');

use PSQR\Git;

//$gitdir = ;
//$repo = new Git\Repository("/home/emanuel/Development/SAGA/saga/.git");
$repo = new Git\Repository("/home/emanuel/Development/saga-workbench/.git");

$repo->init();


$digraph = new \PSQR\Graphviz\DiGraph($repo);

$digraph->generate();
$digraph->output("pdf", __DIR__.'/test.pdf');
