Git Timeline
==

This project is yet another git visualizer. The focus is on git repositories used by teams
of 5+ people using [git-flow workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow) 
to track releases, feature branches, etc.

Get started
--
The following steps should get you started:
1. Clone your repository (clean and preferably as a bare clone):

        git clone git@hostname:some/repo --bare
        
2. Configure this project to point at the repository clone

        cp config.php-dist config.php 
        vim config.php
        
3. Start a php webserver

        cd web
        php -S localhost:23456
        
4. Access the visualization with your browser

        http://localhost:23456