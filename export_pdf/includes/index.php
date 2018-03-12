<div style="margin-top: 50px;">
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4>Эксопртировать данные в PDF</h4>
        </div>
        <div class="panel-body">
            <form class="form-horizontal" method="POST">
                <?php wp_nonce_field(); ?>

                <div class="row">
                    <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12 col-lg-offset-1 col-md-offset-1">
                        <h4>Тип недвижимости</h4>
                        <?php foreach ($categories as $category): ?>
                            <div class="form-group">
                                <div class="checkbox checbox-switch switch-success">
                                    <label>
                                        <input type="checkbox" name="property_category[]" value="<?= $category->term_id; ?>" checked="" />
                                        <span></span>
                                        <?= $category->name; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
                        <h4>Месторасположение</h4>
                        <?php foreach ($locations as $location): ?>
                            <div class="form-group">
                                <div class="checkbox checbox-switch switch-success">
                                    <label>
                                        <input type="checkbox" name="location[]" value="<?= $location->term_id; ?>" checked="" />
                                        <span></span>
                                        <?= $location->name ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-10 col-md-10 col-sm-12 col-xs-12 col-lg-offset-1 col-md-offset-1" style="margin-top: 30px">
                    <div class="form-group">
                        <input type="submit" class="btn btn-success" name="update-pdf" value="Обновить и скачать PDF">
                        <input type="submit" class="btn btn-primary" name="download-pdf" value="Скачать PDF">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>