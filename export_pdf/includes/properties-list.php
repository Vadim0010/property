<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Список недвижимости</title>
    <style>

        @font-face {
            src: url('../lib/fonts/times.ttf');
            font-family: times;
        }

        body, html {
            font-family: times;
        }

        .limiter {
            width: 100%;
            margin: 0 auto;
        }

        .container-table100 {
            width: 100%;
            min-height: 100vh;
            background: #d1d1d1;

            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .wrap-table100 {
            width: 100%;
        }

        table {
            width: 100%;
            background-color: #fff;
        }

        th, td {
            font-weight: unset;
            padding-right: 3px;
        }

        .column100 {
            padding-left: 5px;
        }

        .column100.column1 {
            width: 15px;
            padding-left: 5px;
        }

        .row100.head th {
            padding-top: 24px;
            padding-bottom: 20px;
            text-align: center;
        }

        .row100 td {
            padding-top: 5px;
            padding-bottom: 5px;
        }
        .table100.ver2 td {
            font-size: 14px;
            color: #808080;
            line-height: 1.4;
        }

        .table100.ver2 th {
            font-size: 12px;
            color: #fff;
            line-height: 1.4;
            text-transform: uppercase;

            background-color: #333333;
        }

        .table100.ver2 tbody tr:nth-child(even) {
            background-color: #eaf8e6;
        }

        .table100.ver2 td {
            font-size: 14px;
            color: #808080;
            line-height: 1.4;
        }

        .table100.ver2 th {
            font-size: 12px;
            color: #fff;
            line-height: 1.4;
            text-transform: uppercase;

            background-color: #333333;
        }
    </style>
</head>
<body>
    <div class="limiter">
        <div class="container-table100">
            <div class="wrap-table100">
                <div class="table100 ver2 m-b-110">
                    <table data-vertable="ver2">
                        <thead>
                            <tr class="row100 head">
                                <th class="column100 column1">ID</th>
                                <th class="column100 column2">Название</th>
                                <th class="column100 column3">Тип</th>
                                <th class="column100 column4">Расположение</th>
                                <th class="column100 column5">Цена</th>
                                <th class="column100 column6">Фотография</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php foreach ($properties as $property):
                                $propertyData = getPropertyData($property); ?>

                                <tr class="row100">
                                    <td class="column100 column1"><?= $propertyData['property_code'] ?></td>
                                    <td class="column100 column2"><?= $propertyData['title'] ?></td>
                                    <td class="column100 column3">

                                        <?php if ( is_array($propertyData['types']) ) {
                                            foreach ($propertyData['types'] as $key => $type) {
                                                echo $type->name;

                                                if ($key != (count($propertyData['types'])) - 1) {
                                                    echo ', ';
                                                }
                                            }
                                        } else {
                                            echo $propertyData['types'];
                                        } ?>

                                    </td>
                                    <td class="column100 column4">
                                        <?php if ( is_array($propertyData['locations']) ) {
                                            foreach ($propertyData['locations'] as $key => $location) {
                                                echo $location->name;

                                                if ($key != (count($propertyData['locations'])) - 1) {
                                                    echo ', ';
                                                }
                                            }
                                        } else {
                                            echo $propertyData['locations'];
                                        } ?>
                                    </td>
                                    <td class="column100 column5" data-column="column5"><?= $propertyData['price'] ?></td>
                                    <td class="column100 column6" data-column="column6">
                                        <?php if ($propertyData['photo']): ?>
                                            <img src="<?= $propertyData['photo'] ?>">
                                        <?php else: ?>
                                            <?= 'Фото отсутствует' ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>