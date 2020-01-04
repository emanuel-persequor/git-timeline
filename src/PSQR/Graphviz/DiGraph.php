<?php
namespace PSQR\Graphviz;



class DiGraph
{
    private $dot;

    /**
     * @var \PSQR\Git\Repository
     */
    private $repository;

    public function __construct(\PSQR\Git\Repository $repository)
    {
        $this->repository = $repository;
    }

    public function generate()
    {
        $this->dot = "digraph {\nrankdir=LR; splines=polyline; \n";
        $days = array();
        foreach($this->repository->getCommits() as $c) {
            $this->dot .= "  ref".$c->short."[label=\"".$c->short."\",tooltip=\"".addslashes($c->subject)."\",shape=".("HEAD" == $c->isDefaultBranch() ? 'doubleoctagon':'oval')."];\n";
            foreach($c->getParents() as $p) {
                $links[] = 'ref'.$p->short.' -> '.'ref'.$c->short.";";
            }
            //$day = date("Y-m-d", strtotime($c['author_date']));
            $day = $c->getCommitDate("Y-m-d");
            if(!isset($days[$day])) {
                $days[$day] = array();
            }
            $days[$day][] = "ref".$c->short;
        }
        $this->dot .= "\n".implode("\n", array_unique($links));


        // Refs
        foreach($this->repository->getBranches() as $bIndx => $branch) {
            // if(isset($commits[$branch['ref']]))
            {
                $this->dot .= 'b'.md5($branch->refname).'[label="'.$branch->getVeryShortName().'",shape=box,style=filled,fillcolor=green]'.";\n";
                $this->dot .= "b".md5($branch->refname)." -> ref".$branch->objectname."[style=dashed];\n";
                $this->dot .= "{ rank=same; b".md5($branch->refname)." ref".$branch->objectname."}\n";
            }
        }

        foreach($days as $commits) {
            if(count($commits) > 1) {
                $this->dot .= "{ rank=same; ".implode(" ", $commits)."}\n";
            }
        }


        $this->dot .= "\n}\n";
    }

    public function output($format, $file)
    {
        $tmpDot = tempnam("/tmp/", "dot");
        file_put_contents($tmpDot, $this->dot);
        exec("dot -T".$format." -o \"".$file."\" ".$tmpDot);
        unlink($tmpDot);
    }
}