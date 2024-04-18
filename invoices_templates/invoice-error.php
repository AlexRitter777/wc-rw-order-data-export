<!doctype html>
<html lang="cz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>PDF invoice errors</title>


    <h2>Při vygenerování faktury došlo k následujícím chybám:</h2>

    <p style="color: red; font-size: 20px;"><b><?=!empty($_SESSION['error']) ? $_SESSION['error'] : 'При выполнении программы произошла критическая ошибка! Обратитесь к разрабочтикам!'; ?></b></p>

</head>
<body>

</body>
</html>
