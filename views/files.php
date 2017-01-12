<?php
/**
 * Copyright (c) 2017.
 * @author Nikola Tesic (nikolatesic@gmail.com)
 */

/**
 * Created by PhpStorm.
 * User: Nikola
 * Date: 1/12/2017
 * Time: 9:51 AM
 */
/**
 * @var \Phalcon\Mvc\View $this
 * @var CodeFile[] $files
 */
use \ntesic\generator\generator\CodeFile;
use \ntesic\Helpers\Tag;
?>
<div class="default-view-files">
    <div id="action-toggle" class="btn-group btn-group-xs pull-right">
        <label class="btn btn-success active" title="Filter files that are created">
            <input type="checkbox" value="<?= CodeFile::OP_CREATE ?>" checked> Create
        </label>
        <label class="btn btn-default active" title="Filter files that are unchanged.">
            <input type="checkbox" value="<?= CodeFile::OP_SKIP ?>" checked> Unchanged
        </label>
        <label class="btn btn-warning active" title="Filter files that are overwritten">
            <input type="checkbox" value="<?= CodeFile::OP_OVERWRITE ?>" checked> Overwrite
        </label>
    </div>

    <p>Click on the above <code>Generate</code> button to generate the files selected below:</p>

    <table class="table table-bordered table-striped table-condensed">
        <thead>
        <tr>
            <th class="file">Code File</th>
            <th class="action">Action</th>
            <?php
            $fileChangeExists = false;
            foreach ($files as $file) {
                if ($file->operation !== CodeFile::OP_SKIP) {
                    $fileChangeExists = true;
                    echo '<th><input type="checkbox" id="check-all"></th>';
                    break;
                }
            }
            ?>

        </tr>
        </thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <?php
            if ($file->operation === CodeFile::OP_OVERWRITE) {
                $trClass = 'warning';
            } elseif ($file->operation === CodeFile::OP_SKIP) {
                $trClass = 'active';
            } elseif ($file->operation === CodeFile::OP_CREATE) {
                $trClass = 'success';
            } else {
                $trClass = '';
            }
            ?>
            <tr class="<?= "$file->operation $trClass" ?>">
                <td class="file">
                    <?= \ntesic\Helpers\Tag::linkTo([
                        'action' => $this->url->get($this->router->getModuleName() . '/' . $this->router->getControllerName() . '/preview', ['file' => $file->id], true),
                        'text' => $file->getRelativePath(),
                        'class' => 'preview-code',
                        'data-title' => $file->getRelativePath()
                    ]) ?>
                    <?php if ($file->operation === CodeFile::OP_OVERWRITE): ?>
                        <?= \ntesic\Helpers\Tag::linkTo([
                            'action' => $this->url->get($this->router->getModuleName() . '/' . $this->router->getControllerName() . '/diff', ['file' => $file->id], true),
                            'text' => 'diff',
                            'class' => 'diff-code label label-warning',
                            'data-title' => $file->getRelativePath()
                        ]) ?>
                    <?php endif; ?>
                </td>
                <td class="action">
                    <?php
                    if ($file->operation === CodeFile::OP_SKIP) {
                        echo 'unchanged';
                    } else {
                        echo $file->operation;
                    }
                    ?>
                </td>
                <?php if ($fileChangeExists): ?>
                    <td class="check">
                        <?php
                        if ($file->operation === CodeFile::OP_SKIP) {
                            echo '&nbsp;';
                        } else {
                            echo Tag::checkField([
                                "answers[{$file->id}]",
                                'value' => isset($answers) ? isset($answers[$file->id]) : ($file->operation === CodeFile::OP_CREATE),
                            ]);
                        }
                        ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="modal fade" id="preview-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <div class="btn-group pull-left">
                        <a class="modal-previous btn btn-xs btn-default" href="#" title="Previous File (Left Arrow)"><span class="glyphicon glyphicon-arrow-left"></span></a>
                        <a class="modal-next btn btn-xs btn-default" href="#" title="Next File (Right Arrow)"><span class="glyphicon glyphicon-arrow-right"></span></a>
                        <a class="modal-refresh btn btn-xs btn-default" href="#" title="Refresh File (R)"><span class="glyphicon glyphicon-refresh"></span></a>
                        <a class="modal-checkbox btn btn-xs btn-default" href="#" title="Check This File (Space)"><span class="glyphicon"></span></a>
                        &nbsp;
                    </div>
                    <strong class="modal-title pull-left">Modal title</strong>
                    <span class="modal-copy-hint pull-right"><kbd>CTRL</kbd>+<kbd>C</kbd> to copy</span>
                    <div id="clipboard-container"><textarea id="clipboard"></textarea></div>
                    <div class="clearfix"></div>
                </div>
                <div class="modal-body">
                    <p>Please wait ...</p>
                </div>
            </div>
        </div>
    </div>
</div>
