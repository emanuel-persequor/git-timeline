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
    private $history = array();

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
        $item->addBranch($this);
    }

}