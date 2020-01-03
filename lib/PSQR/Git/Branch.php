<?php

namespace PSQR\Git;


class Branch
{
    private $repository;
    public $ref;
    public $name;
    public $short;
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
        $this->ref = $logData['ref'];
        $this->name = $logData['name'];
        $this->short = substr($this->name, 7);
    }

    public function getRef()
    {
        return $this->ref;
    }

    public function addCommit(Commit $item)
    {
        $this->history[] = $item;
    }

    public function getVeryShortName()
    {
        $parts = explode("/", $this->name);
        return array_pop($parts);
    }

    public function matchName($name)
    {
        return substr_compare($this->name, $name, -strlen($name)) === 0;
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

}