<div style="margin-top: 50px;" id="container-dreamvilla-import-data">
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4>Импорт данных о недвижимости</h4>
        </div>
        <div class="panel-body">
            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo '<p>Записей добавлено: ' . count($this->properties['new']) . '</p>'; ?>
                    <?php echo '<p>Записей обновлено: ' . count($this->properties['edit']) . '</p>'; ?>
                </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-lg-10 col-md-10 col-sm-11 col-xs-11 col-lg-offset-1 col-md-offset-1 col-sm-offset-1 col-xs-offset-1">
                    <form class="form-horizontal" method="POST">
                        <?php wp_nonce_field(); ?>
                        <input type="hidden" id="user-id" name="user_ID" value="<?php echo (int) $user_ID ?>" />
                        <div class="form-group">
                            <input
                                    type="checkbox"
                                    class="form-control"
                                    name="dreamvilla-import-data-check-update"
                                    id="dreamvilla-import-data-check-update"
                            >
                            <label for="dreamvilla-import-data-check-update" class="control-label">Добавить новые и обновить существующие записи</label>
                        </div>
                        <div class="form-group">
                            <input
                                    type="checkbox"
                                    class="form-control"
                                    name="dreamvilla-import-data-check-onlyEdit"
                                    id="dreamvilla-import-data-check-onlyEdit"
                            >
                            <label for="dreamvilla-import-data-check-onlyEdit" class="control-label">Обновить только существующие записи</label>
                        </div>
                        <div class="form-group">
                            <input
                                    type="checkbox"
                                    class="form-control"
                                    name="dreamvilla-import-data-check-image"
                                    id="dreamvilla-import-data-check-image"
                            >
                            <label for="dreamvilla-import-data-check-image" class="control-label">Обновить только изображения у существующих записей</label>
                        </div>
                        <div class="form-group">
                            <label class="radio-inline">
                                <input type="radio" name="dreamvilla-import-data-publish" id="dreamvilla-import-data-draft" value="draft" checked>
                                Добавить в черновик
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="dreamvilla-import-data-publish" id="dreamvilla-import-data-publish" value="publish">
                                Опубликовать
                            </label>
                        </div>
                        <div class="form-group">
                            <button class="btn btn-success" id="dreamvilla-import-data-upload">Загрузить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php if (count($xmlErrors)): ?>
        <div class="panel panel-danger">
            <div class="panel-body">
                <?php foreach ($xmlErrors as $error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>