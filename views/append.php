<?php
/**
 * Copyright (c) 2017.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

/**
 * Created by PhpStorm.
 * User: Nikola
 * Date: 1/12/2017
 * Time: 9:44 AM
 */
/**
 * @var \Phalcon\Mvc\View $this
 */
?>
<hr />
<?php
if (isset($results)) {
    echo $this->partial('results');
} elseif (isset($files)) {
    echo $this->partial('files');
}
?>