<?php
namespace PSQR\HTML;


use PSQR\Git\Branch;
use PSQR\Git\Commit;

class Json
{
    /**
     * @var \PSQR\Git\Repository
     */
    private $repository;

    private $jsonStr;

    public function __construct(\PSQR\Git\Repository $repository)
    {
        $this->repository = $repository;
    }

    public function generate()
    {
        $json = array();

        // Calculate timeline params
        $json['firstTime'] = $this->repository->getFirstCommit()->getFirstTime();
        $json['lastTime'] = $this->repository->getLastCommit()->getLastTime();


        // Branches
        $json['branches'] = array();
        $branches = $this->repository->getBranches();
        usort($branches, function($b1,$b2){return $b2->getFirstCommit()->getFirstTime()-$b1->getFirstCommit()->getFirstTime();});
        foreach($branches as $b)
        {
            $json['branches'][$b->objectname] = array(
                'shortname'=>$b->getVeryShortName(),
                'refname'=>$b->refname,
                'firstTime'=>$b->getFirstCommit()->getFirstTime(),
                'lastTime'=>$b->getLastCommit()->getLastTime(),
                'type'=>$b->getType(),
            );
        }

        // Add commits
        $json['commits'] = array();
        foreach($this->repository->getCommits() as $c)
        {
            $json['commits'][$c->sha] = array(
                'firstTime'=>$c->getFirstTime(),
                'lastTime'=>$c->getLastTime(),
                'branch'=>$c->branch->objectname,
                'author'=>$c->getAuthor(),
                'subject'=>$c->getSubject(),
                'type'=>$this->getCommitType($c),
            );
        }

        // Print tags
        $json['tags'] = array();
        foreach($this->repository->tags as $tag => $commit)
        {
            $json['tags'][$tag] = $commit->sha;
        }

        $this->jsonStr = json_encode($json);
    }

    public function output($file=null)
    {
        if(is_null($file))
        {
            print($this->jsonStr);
        }
        else
        {
            file_put_contents($file, $this->jsonStr);
        }
    }

    private function getCommitType(Commit $c)
    {
        if(count($c->getParents()) == 0)
        {
            return "root";
        }
        if($c->isEndCommit())
        {
            return "end";
        }
        if(count($c->getParents()) > 1)
        {
            return "merge";
        }
        return "normal";
    }
}