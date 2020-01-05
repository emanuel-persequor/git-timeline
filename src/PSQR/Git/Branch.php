<?php

namespace PSQR\Git;


class Branch
{
    private $repository;
    public $objectname;
    public $refname;
    //public $short;
    /**
     * @var Commit[]
     */
    public $history = array();
    /**
     * @var Commit
     */
    private $_firstCommit = null, $_lastCommit = null;


    public function __construct(Repository $repository, $logData)
    {
        $this->repository = $repository;
        $this->objectname = $logData['objectname'];
        $this->refname = $logData['refname'];
        //$this->short = substr($this->name, 7);
    }

    public function addCommit(Commit $item)
    {
        $this->history[] = $item;
    }

    public function getVeryShortName()
    {
        $parts = explode("/", $this->refname);
        return array_pop($parts);
    }

    public function matchName($name)
    {
        return substr_compare($this->refname, $name, -strlen($name)) === 0;
    }

    public function getFirstCommit()
    {
        if(is_null($this->_firstCommit))
        {
            foreach($this->history as $c)
            {
                if(is_null($this->_firstCommit) ||
                    $c->getAuthorDate() < $this->_firstCommit->getAuthorDate() ||
                    $c->getCommitDate() < $this->_firstCommit->getCommitDate()
                )
                {
                    $this->_firstCommit = $c;
                }
            }
        }
        return $this->_firstCommit;
    }

    public function getLastCommit()
    {
        if(is_null($this->_lastCommit))
        {
            foreach($this->history as $c)
            {
                if(is_null($this->_lastCommit) ||
                    $c->getAuthorDate() > $this->_lastCommit->getAuthorDate() ||
                    $c->getCommitDate() > $this->_lastCommit->getCommitDate()
                )
                {
                    $this->_lastCommit = $c;
                }
            }
        }
        return $this->_lastCommit;
    }

    public function isDefaultBranch()
    {
        return $this->getVeryShortName() == $this->repository->defaultBranch;
    }

    public function isRelease()
    {
        return strpos($this->refname, "release/") !== false;
    }

    public function isTag()
    {
        return strpos($this->refname, "tags/") !== false;
    }

    public function isUnmerged()
    {
        foreach($this->history as $c)
        {
            if($c->isEndCommit())
            {
                return true;
            }
        }
        return false;
    }

    public function overlapDateRange($from, $to)
    {
        $selfFrom = $this->getFirstCommit()->getFirstTime();
        if($selfFrom > $to)
        {
            return false;
        }
        $selfTo = $this->getLastCommit()->getLastTime();
        if($selfTo < $from)
        {
            return false;
        }
        return true;
    }

    public function getType()
    {
        if($this->isDefaultBranch())
        {
            return "master";
        }
        if($this->isRelease())
        {
            return "release";
        }
        if($this->isTag())
        {
            return "tag";
        }
        return "feature";
    }
}